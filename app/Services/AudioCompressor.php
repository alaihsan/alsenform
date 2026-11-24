<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\ExecutableFinder;
use Throwable;

/**
 * Smart compression of sound for questions, choices and spoken answers: whatever the teacher or
 * the student hands in (a recording of the browser, a WAV, an MP3 from a phone, the sound of a
 * video) is kept as a small mono MP3 with an even loudness, which every browser plays and which
 * loads quickly on a busy school Wi-Fi. A file that is already light is kept as it is; without
 * ffmpeg on the server every file is kept as it is.
 */
class AudioCompressor
{
    /** Questions and choices: listening material, music included, stays clear at this bitrate. */
    public const LISTENING_KBPS = 64;

    /** Spoken answers: a voice needs less. */
    public const SPEECH_KBPS = 48;

    /**
     * The sound files people have: recordings of browsers (WebM, Ogg, MP4), of phones (M4A, AMR,
     * 3GP), audio files, and videos whose sound is kept.
     */
    public const EXTENSIONS = ['mp3', 'm4a', 'aac', 'wav', 'ogg', 'oga', 'opus', 'webm', 'weba', 'flac', 'amr', '3gp', '3gpp', 'mp4', 'mpga', 'mpeg', 'caf'];

    /** The types of those files, for the mimetypes rule. */
    public const MIME_TYPES = ['audio/*', 'video/webm', 'video/mp4', 'video/3gpp', 'video/quicktime', 'application/ogg'];

    /** Formats every browser plays: an upload in one of them is kept when compressing would not make it smaller. */
    protected const PLAYABLE_EVERYWHERE = ['mp3', 'aac'];

    /** Longest wait for ffmpeg; a lesson-long recording is compressed well within it. */
    protected const TIMEOUT_SECONDS = 240;

    protected ?string $ffmpeg = null;

    protected ?string $ffprobe = null;

    protected bool $resolved = false;

    /**
     * Stores the sound of the upload on the disk, compressed when that makes it lighter.
     *
     * @return array{path: string, mime: string, size: int, originalSize: int, duration: float|null, compressed: bool}
     */
    public function store(UploadedFile $file, string $directory, string $disk, int $bitrateKbps = self::LISTENING_KBPS): array
    {
        $source = (string) $file->getRealPath();
        $originalSize = (int) $file->getSize();
        $probe = $this->probe($source);

        if ($this->isAvailable() && ! $this->isLightEnough($probe, $originalSize, $bitrateKbps)) {
            $compressed = $this->compress($source, $bitrateKbps);

            if ($compressed !== null) {
                try {
                    $size = (int) filesize($compressed);
                    $keepsOriginal = $size >= $originalSize && in_array($probe['codec'] ?? null, self::PLAYABLE_EVERYWHERE, true);

                    if (! $keepsOriginal) {
                        $path = trim($directory, '/').'/'.Str::random(40).'.mp3';
                        $stream = fopen($compressed, 'rb');
                        Storage::disk($disk)->put($path, $stream);
                        if (is_resource($stream)) {
                            fclose($stream);
                        }

                        return [
                            'path' => $path,
                            'mime' => 'audio/mpeg',
                            'size' => $size,
                            'originalSize' => $originalSize,
                            'duration' => $this->probe($compressed)['duration'] ?? $probe['duration'] ?? null,
                            'compressed' => true,
                        ];
                    }
                } finally {
                    @unlink($compressed);
                }
            }
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: ($file->guessExtension() ?? 'audio'));
        $extension = in_array($extension, self::EXTENSIONS, true) ? $extension : 'audio';

        return [
            'path' => $file->storeAs(trim($directory, '/'), Str::random(40).'.'.$extension, $disk),
            'mime' => $this->audioMime((string) $file->getMimeType()),
            'size' => $originalSize,
            'originalSize' => $originalSize,
            'duration' => $probe['duration'] ?? null,
            'compressed' => false,
        ];
    }

    /**
     * What ffprobe says of the sound of a file, or null without ffprobe or when it cannot read it.
     *
     * @return array{hasAudio: bool, codec: string|null, kbps: int|null, channels: int|null, duration: float|null}|null
     */
    public function probe(string $path): ?array
    {
        $ffprobe = $this->binary('ffprobe');
        if ($ffprobe === null) {
            return null;
        }

        try {
            $result = Process::timeout(30)->run([
                $ffprobe, '-v', 'error', '-print_format', 'json', '-show_format', '-show_streams', '-select_streams', 'a:0', $path,
            ]);
        } catch (Throwable) {
            return null;
        }

        $data = json_decode($result->output(), true);
        if (! $result->successful() || ! is_array($data)) {
            return null;
        }

        $stream = $data['streams'][0] ?? null;
        $duration = $data['format']['duration'] ?? $stream['duration'] ?? null;
        $bitRate = $stream['bit_rate'] ?? $data['format']['bit_rate'] ?? null;

        return [
            'hasAudio' => is_array($stream),
            'codec' => is_array($stream) && isset($stream['codec_name']) ? (string) $stream['codec_name'] : null,
            'kbps' => is_numeric($bitRate) ? (int) round($bitRate / 1000) : null,
            'channels' => is_array($stream) && is_numeric($stream['channels'] ?? null) ? (int) $stream['channels'] : null,
            'duration' => is_numeric($duration) ? round((float) $duration, 2) : null,
        ];
    }

    /**
     * Whether ffmpeg is on the server to compress with.
     */
    public function isAvailable(): bool
    {
        return $this->binary('ffmpeg') !== null;
    }

    /**
     * An MP3 already at or below the target (with a little room) is kept: compressing it again
     * would only cost quality and time.
     *
     * @param  array{hasAudio: bool, codec: string|null, kbps: int|null, channels: int|null, duration: float|null}|null  $probe
     */
    protected function isLightEnough(?array $probe, int $size, int $bitrateKbps): bool
    {
        if (($probe['codec'] ?? null) !== 'mp3') {
            return false;
        }

        $kbps = $probe['kbps'] ?? (($probe['duration'] ?? 0) > 0 ? (int) round($size * 8 / 1000 / $probe['duration']) : null);

        return $kbps !== null && $kbps <= (int) round($bitrateKbps * 1.25);
    }

    /**
     * The sound as a mono MP3 in a temporary file: no picture or tags, rumble below 60 Hz cut and
     * the loudness evened out (EBU R128), so quiet recordings are as easy to hear as loud ones.
     */
    protected function compress(string $source, int $bitrateKbps): ?string
    {
        $target = tempnam(sys_get_temp_dir(), 'falcon_audio_');
        if ($target === false) {
            return null;
        }
        @unlink($target);
        $target .= '.mp3';

        try {
            $result = Process::timeout(self::TIMEOUT_SECONDS)->run([
                (string) $this->binary('ffmpeg'), '-hide_banner', '-nostdin', '-loglevel', 'error', '-y',
                '-i', $source,
                '-vn', '-sn', '-dn', '-map_metadata', '-1',
                '-af', 'highpass=f=60,loudnorm=I=-16:TP=-1.5:LRA=11',
                '-ac', '1', '-ar', '44100',
                '-codec:a', 'libmp3lame', '-b:a', $bitrateKbps.'k',
                $target,
            ]);
        } catch (Throwable $exception) {
            Log::warning('Audio tidak dikompres: '.$exception->getMessage());
            @unlink($target);

            return null;
        }

        if (! $result->successful() || ! is_file($target) || filesize($target) === 0) {
            Log::warning('Audio tidak dikompres: '.Str::limit(trim($result->errorOutput()), 300));
            @unlink($target);

            return null;
        }

        return $target;
    }

    /**
     * ffmpeg or ffprobe: as configured, else found in the PATH or where Homebrew and Linux put it
     * (the web server's PATH often lacks those folders).
     */
    protected function binary(string $name): ?string
    {
        if (! $this->resolved) {
            $finder = new ExecutableFinder;
            $folders = ['/opt/homebrew/bin', '/usr/local/bin', '/usr/bin'];
            $configured = (array) config('services.ffmpeg', []);
            $enabled = filter_var($configured['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN);

            $this->ffmpeg = $enabled ? ($this->usable($configured['ffmpeg'] ?? null) ?? $finder->find('ffmpeg', null, $folders)) : null;
            $this->ffprobe = $enabled ? ($this->usable($configured['ffprobe'] ?? null) ?? $finder->find('ffprobe', null, $folders)) : null;
            $this->resolved = true;
        }

        return $name === 'ffmpeg' ? $this->ffmpeg : $this->ffprobe;
    }

    /**
     * The type of a sound kept as sent: a recording in a video container is still sound.
     */
    protected function audioMime(string $mime): string
    {
        return match ($mime) {
            'video/webm' => 'audio/webm',
            'video/mp4', 'video/quicktime' => 'audio/mp4',
            'video/3gpp' => 'audio/3gpp',
            'application/ogg' => 'audio/ogg',
            default => str_starts_with($mime, 'audio/') ? $mime : 'audio/mpeg',
        };
    }

    protected function usable(mixed $path): ?string
    {
        return is_string($path) && $path !== '' && is_executable($path) ? $path : null;
    }
}
