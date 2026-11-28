<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use FilesystemIterator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Throwable;

/**
 * The files of the exams next to the database copies (see DatabaseBackup): pictures, videos and
 * sounds of the questions and avatars (storage/app/public), and what students handed in, their
 * uploads and spoken answers (storage/app/private). The database only knows where they are.
 *
 * Kept as a mirror in "berkas" in the backup folder: only new and changed files are copied, so a
 * daily run stays quick. Nothing is removed from it, also when a file is gone from the server:
 * a disk that lost its files must not empty the backup as well.
 */
class FileBackup
{
    /** The folder in the backup folder that holds the files. */
    public const FOLDER = 'berkas';

    /** What the last run did, kept in the mirror. */
    public const STATE_FILE = '.backup-berkas.json';

    /** Room left on the backup disk after copying, so the server itself keeps working. */
    public const SPARE_BYTES = 50 * 1024 * 1024;

    /**
     * Copy the new and changed files to the mirror.
     *
     * @return array{path: string, files: int, bytes: int, copied: int, copiedBytes: int, finishedAt: CarbonImmutable}
     *
     * @throws RuntimeException With what went wrong and how to fix it, in Indonesian.
     */
    public function run(): array
    {
        $target = $this->directory();
        File::ensureDirectoryExists($target, 0750);

        $files = 0;
        $bytes = 0;
        $toCopy = [];

        foreach ($this->sources() as $name => $root) {
            foreach ($this->filesIn($root) as $file) {
                $relative = substr($file->getPathname(), strlen($root) + 1);
                $destination = $target.DIRECTORY_SEPARATOR.$name.DIRECTORY_SEPARATOR.$relative;
                $files++;
                $bytes += $file->getSize();

                if (! $this->isSame($file, $destination)) {
                    $toCopy[] = [$file, $destination];
                }
            }
        }

        $copiedBytes = array_sum(array_map(fn (array $copy): int => $copy[0]->getSize(), $toCopy));
        $free = $this->freeSpace($target);

        if ($free !== null && $copiedBytes + self::SPARE_BYTES > $free) {
            throw new RuntimeException('Ruang disk untuk backup berkas tidak cukup: perlu '.$this->bytes($copiedBytes).', tersisa '.$this->bytes($free).'. Kosongkan disk, atau arahkan BACKUP_PATH di .env ke disk lain.');
        }

        foreach ($toCopy as [$file, $destination]) {
            $this->copy($file, $destination);
        }

        $finishedAt = CarbonImmutable::now();
        $state = ['finishedAt' => $finishedAt->toISOString(), 'files' => $files, 'bytes' => $bytes, 'copied' => count($toCopy), 'copiedBytes' => $copiedBytes];
        File::put($target.DIRECTORY_SEPARATOR.self::STATE_FILE, json_encode($state, JSON_PRETTY_PRINT));

        return array_merge(['path' => $target], $state, ['finishedAt' => $finishedAt]);
    }

    /**
     * What the last run did, or null when the files were never backed up.
     *
     * @return array{path: string, files: int, bytes: int, copied: int, copiedBytes: int, finishedAt: CarbonImmutable}|null
     */
    public function latest(): ?array
    {
        $state = json_decode((string) @file_get_contents($this->directory().DIRECTORY_SEPARATOR.self::STATE_FILE), true);

        if (! is_array($state) || ! is_string($state['finishedAt'] ?? null)) {
            return null;
        }

        try {
            $finishedAt = CarbonImmutable::parse($state['finishedAt']);
        } catch (Throwable) {
            return null;
        }

        return [
            'path' => $this->directory(),
            'files' => (int) ($state['files'] ?? 0),
            'bytes' => (int) ($state['bytes'] ?? 0),
            'copied' => (int) ($state['copied'] ?? 0),
            'copiedBytes' => (int) ($state['copiedBytes'] ?? 0),
            'finishedAt' => $finishedAt,
        ];
    }

    /**
     * Whether the last run is older than the given hours (or there was none).
     */
    public function isOlderThan(float $hours): bool
    {
        $latest = $this->latest();

        return $latest === null || $latest['finishedAt']->lt(now()->subMinutes((int) round($hours * 60)));
    }

    public function directory(): string
    {
        return rtrim((string) config('maintenance.backup.path'), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.self::FOLDER;
    }

    /**
     * How to put the files back, for the person who needs it.
     */
    public function restoreHint(): string
    {
        $hints = [];

        foreach ($this->sources() as $name => $root) {
            $hints[] = 'salin isi '.$this->directory().DIRECTORY_SEPARATOR.$name.' ke '.$root;
        }

        return ucfirst(implode(', lalu ', $hints)).'.';
    }

    /**
     * The folders copied, by their name in the mirror: the disks of the application.
     *
     * @return array<string, string>
     */
    protected function sources(): array
    {
        return [
            'public' => rtrim(Storage::disk('public')->path(''), DIRECTORY_SEPARATOR),
            'private' => rtrim(Storage::disk('local')->path(''), DIRECTORY_SEPARATOR),
        ];
    }

    /**
     * The files under a folder: no links, no .gitignore, nothing inside the backup folder itself.
     *
     * @return iterable<SplFileInfo>
     */
    protected function filesIn(string $root): iterable
    {
        if (! is_dir($root)) {
            return [];
        }

        $backupFolder = rtrim((string) config('maintenance.backup.path'), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            /** @var SplFileInfo $file */
            if ($file->isLink() || ! $file->isFile() || $file->getFilename() === '.gitignore' || str_starts_with($file->getPathname(), $backupFolder)) {
                continue;
            }

            yield $file;
        }
    }

    /**
     * Already in the mirror as it is now: the same size and time.
     */
    protected function isSame(SplFileInfo $file, string $destination): bool
    {
        return is_file($destination) && filesize($destination) === $file->getSize() && filemtime($destination) === $file->getMTime();
    }

    /**
     * Copied under another name first, so a copy cut short is never taken for a good one.
     */
    protected function copy(SplFileInfo $file, string $destination): void
    {
        File::ensureDirectoryExists(dirname($destination), 0750);
        $partial = $destination.'.partial';

        if (! @copy($file->getPathname(), $partial)) {
            @unlink($partial);

            throw new RuntimeException('Berkas '.$file->getPathname().' tidak dapat disalin ke backup. Periksa izin folder '.$this->directory().'.');
        }

        @touch($partial, $file->getMTime());
        rename($partial, $destination);
        // Students' answers: for the server's own account only.
        @chmod($destination, 0640);
    }

    /**
     * Bytes free on the disk of the backup folder, or null when the system cannot tell.
     */
    protected function freeSpace(string $directory): ?int
    {
        $free = @disk_free_space($directory);

        return $free === false ? null : (int) $free;
    }

    protected function bytes(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 KB';
        }

        return $bytes >= 1073741824
            ? number_format($bytes / 1073741824, 1, ',', '.').' GB'
            : ($bytes >= 1048576 ? number_format($bytes / 1048576, 1, ',', '.').' MB' : number_format(max(1, $bytes / 1024), 0, ',', '.').' KB');
    }
}
