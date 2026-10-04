<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Host-independent URLs for files stored on the public disk.
 *
 * Absolute URLs such as http://192.168.1.10:8000/storage/media/a.webp break as
 * soon as the server IP changes or the quiz is opened through an Expose tunnel.
 * Media is therefore referenced with a root-relative path (/storage/...), which
 * the browser always resolves against the address the student actually used.
 */
class MediaUrl
{
    /**
     * The URL for a path stored on the public disk.
     */
    public function forPublicPath(string $path): string
    {
        return '/storage/'.ltrim($path, '/');
    }

    /**
     * Host suffixes used by this application on a school network or through a tunnel.
     *
     * @var list<string>
     */
    protected const LOCAL_HOST_SUFFIXES = [
        '.localhost', '.local', '.test', '.lan', '.home', '.internal', '.intranet', '.localdomain', '.home.arpa',
        '.sharedwithexpose.com', '.expose.dev', '.expose.sh',
    ];

    /**
     * Turn an absolute URL that points at this application's public storage
     * into a root-relative one. External URLs are returned untouched.
     */
    public function normalize(mixed $url): mixed
    {
        if (! is_string($url) || $url === '') {
            return $url;
        }

        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return $url;
        }

        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true) || ! $this->isApplicationHost($parts['host'])) {
            return $url;
        }

        $path = $parts['path'] ?? '';
        if (! str_starts_with($path, '/storage/')) {
            return $url;
        }

        $storedPath = rawurldecode(substr($path, strlen('/storage/')));
        if ($storedPath === '' || str_contains($storedPath, '..') || ! Storage::disk('public')->exists($storedPath)) {
            return $url;
        }

        return $path.(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    /**
     * Determine if a host is one this application was (or is) reached through:
     * a LAN / loopback IP, a local host name, an Expose tunnel or the configured URL.
     */
    protected function isApplicationHost(string $host): bool
    {
        $host = strtolower(trim($host, '[]'));

        if ($host === 'localhost' || ! str_contains($host, '.') && ! str_contains($host, ':')) {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
        }

        foreach (self::LOCAL_HOST_SUFFIXES as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return true;
            }
        }

        $knownHosts = [strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST))];
        if (app()->bound('request')) {
            $knownHosts[] = strtolower(request()->getHost());
        }

        return in_array($host, array_filter($knownHosts), true);
    }

    /**
     * Normalize the media URLs embedded in a list of quiz questions.
     *
     * @param  array<int, mixed>|null  $questions
     * @return array<int, mixed>
     */
    public function normalizeQuestions(?array $questions): array
    {
        return array_map(function (mixed $question): mixed {
            if (! is_array($question) || ! is_array($question['media'] ?? null)) {
                return $question;
            }

            $question['media'] = array_map(function (mixed $media): mixed {
                if (is_array($media) && array_key_exists('url', $media)) {
                    $media['url'] = $this->normalize($media['url']);
                }

                return $media;
            }, $question['media']);

            return $question;
        }, $questions ?? []);
    }
}
