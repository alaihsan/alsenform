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

        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
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
