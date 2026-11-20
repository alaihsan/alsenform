<?php

namespace App\Support;

use FilesystemIterator;
use Illuminate\Support\Facades\Process;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * The bundle of the pages in public/build, and whether it still matches the code it is built from.
 */
class AssetBuild
{
    /** The command that builds the bundle; the tests fake it (tests/TestCase.php). */
    public const COMMAND = 'npm run build';

    /**
     * Builds the bundle again (it replaces public/build), the build's output given to $output as it
     * comes. Whether it worked.
     *
     * @param  (callable(string, string): void)|null  $output
     */
    public function build(?callable $output = null): bool
    {
        return Process::path(base_path())->timeout(600)->run(self::COMMAND, $output)->successful();
    }

    public function manifestPath(): string
    {
        return public_path('build/manifest.json');
    }

    /**
     * The files the bundle in public/build is built from.
     *
     * @return list<string>
     */
    public function sourcePaths(): array
    {
        return array_map(fn (string $path): string => base_path($path), ['resources/js', 'resources/css', 'vite.config.ts', 'tailwind.config.js', 'package.json']);
    }

    /**
     * Whether the bundle is older than its sources: after "git pull" or a merge without "npm run
     * build", browsers would still get the previous pages, whose new buttons do nothing.
     *
     * @param  list<string>|null  $sourcePaths
     */
    public function isOutdated(?string $manifestPath = null, ?array $sourcePaths = null): bool
    {
        $builtAt = @filemtime($manifestPath ?? $this->manifestPath());

        if ($builtAt === false) {
            return true;
        }

        foreach ($sourcePaths ?? $this->sourcePaths() as $path) {
            if (is_file($path) && filemtime($path) > $builtAt) {
                return true;
            }

            if (! is_dir($path)) {
                continue;
            }

            /** @var SplFileInfo $file */
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)) as $file) {
                if ($file->isFile() && $file->getMTime() > $builtAt) {
                    return true;
                }
            }
        }

        return false;
    }
}
