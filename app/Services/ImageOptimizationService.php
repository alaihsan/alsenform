<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageOptimizationService
{
    /**
     * Max dimensions and quality for optimized web display.
     */
    protected int $maxWidth = 1600;

    protected int $maxHeight = 1600;

    protected int $quality = 82;

    /**
     * Optimize an uploaded image and store it in the specified disk/directory.
     * Converts to WebP if supported, or high-efficiency JPEG.
     */
    public function optimizeAndStore(UploadedFile|string $file, string $directory = 'media', string $disk = 'public'): string
    {
        $realPath = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        $mime = $file instanceof UploadedFile ? $file->getMimeType() : (mime_content_type($realPath) ?: '');

        // If not an image or is SVG/GIF, store directly without lossy re-encoding
        if (! str_starts_with($mime, 'image/') || in_array($mime, ['image/svg+xml', 'image/gif'], true)) {
            if ($file instanceof UploadedFile) {
                return $file->store($directory, $disk);
            }
            $filename = Str::random(40).'.'.pathinfo($realPath, PATHINFO_EXTENSION);
            $targetPath = trim($directory, '/').'/'.$filename;
            Storage::disk($disk)->put($targetPath, file_get_contents($realPath));

            return $targetPath;
        }

        // Try GD optimization
        if (extension_loaded('gd')) {
            $optimizedPath = $this->optimizeWithGd($realPath, $mime, $directory, $disk);
            if ($optimizedPath) {
                return $optimizedPath;
            }
        }

        // Fallback: standard store
        if ($file instanceof UploadedFile) {
            return $file->store($directory, $disk);
        }

        $filename = Str::random(40).'.'.pathinfo($realPath, PATHINFO_EXTENSION);
        $targetPath = trim($directory, '/').'/'.$filename;
        Storage::disk($disk)->put($targetPath, file_get_contents($realPath));

        return $targetPath;
    }

    /**
     * Optimize raw image bytes and store to disk.
     */
    public function optimizeAndStoreBytes(string $imageBytes, string $fallbackExtension = 'jpg', string $directory = 'media', string $disk = 'public'): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'alsen_img_');
        file_put_contents($tempFile, $imageBytes);

        try {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->buffer($imageBytes) ?: 'image/jpeg';

            if (! str_starts_with($mime, 'image/') || in_array($mime, ['image/svg+xml', 'image/gif'], true)) {
                $filename = Str::random(40).'.'.$fallbackExtension;
                $targetPath = trim($directory, '/').'/'.$filename;
                Storage::disk($disk)->put($targetPath, $imageBytes);

                return $targetPath;
            }

            if (extension_loaded('gd')) {
                $optimizedPath = $this->optimizeWithGd($tempFile, $mime, $directory, $disk);
                if ($optimizedPath) {
                    return $optimizedPath;
                }
            }

            $filename = Str::random(40).'.'.$fallbackExtension;
            $targetPath = trim($directory, '/').'/'.$filename;
            Storage::disk($disk)->put($targetPath, $imageBytes);

            return $targetPath;
        } finally {
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }
    }

    /**
     * Optimize image using GD library.
     */
    protected function optimizeWithGd(string $path, string $mime, string $directory, string $disk): ?string
    {
        try {
            $image = match ($mime) {
                'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($path),
                'image/png' => @imagecreatefrompng($path),
                'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null,
                'image/bmp', 'image/x-ms-bmp', 'image/x-bmp' => function_exists('imagecreatefrombmp') ? @imagecreatefrombmp($path) : null,
                default => null,
            };

            if (! $image) {
                return null;
            }

            // Fix EXIF orientation for JPEGs if exif extension is present
            if (function_exists('exif_read_data') && in_array($mime, ['image/jpeg', 'image/jpg'], true)) {
                $exif = @exif_read_data($path);
                if (! empty($exif['Orientation'])) {
                    $image = match ($exif['Orientation']) {
                        3 => imagerotate($image, 180, 0),
                        6 => imagerotate($image, -90, 0),
                        8 => imagerotate($image, 90, 0),
                        default => $image,
                    };
                }
            }

            $origWidth = imagesx($image);
            $origHeight = imagesy($image);

            // Calculate scaled dimensions
            $newWidth = $origWidth;
            $newHeight = $origHeight;

            if ($origWidth > $this->maxWidth || $origHeight > $this->maxHeight) {
                $ratio = min($this->maxWidth / $origWidth, $this->maxHeight / $origHeight);
                $newWidth = (int) round($origWidth * $ratio);
                $newHeight = (int) round($origHeight * $ratio);
            }

            // Create canvas
            $resized = imagecreatetruecolor($newWidth, $newHeight);

            // Handle transparency for PNG / WebP
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
            imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $transparent);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

            imagedestroy($image);

            // Output to WebP if supported, otherwise JPEG
            ob_start();
            $supportsWebp = function_exists('imagewebp');
            $extension = $supportsWebp ? 'webp' : 'jpg';

            if ($supportsWebp) {
                imagewebp($resized, null, $this->quality);
            } else {
                imagejpeg($resized, null, $this->quality);
            }
            $outputData = ob_get_clean();
            imagedestroy($resized);

            if (! $outputData) {
                return null;
            }

            $filename = Str::random(40).'.'.$extension;
            $targetPath = trim($directory, '/').'/'.$filename;

            Storage::disk($disk)->put($targetPath, $outputData);

            return $targetPath;
        } catch (\Throwable) {
            return null;
        }
    }
}
