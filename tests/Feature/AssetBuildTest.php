<?php

use App\Console\Commands\ServeLanCommand;
use App\Support\AssetBuild;
use Illuminate\Console\OutputStyle;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/*
 * The tests may run in the checkout the school's server serves. No test may build its pages for
 * real: a build empties public/build under the students' open exams (it happened once, from a test
 * of lan:serve, while the code was newer than the bundle). tests/TestCase.php pretends every
 * "npm run build".
 */

beforeEach(function () {
    $this->directory = storage_path('framework/testing/bundle-'.uniqid());
    File::ensureDirectoryExists("{$this->directory}/build");
    File::ensureDirectoryExists("{$this->directory}/js/pages");
    file_put_contents("{$this->directory}/js/pages/Dashboard.vue", '<template />');
});

afterEach(fn () => File::deleteDirectory($this->directory));

/**
 * lan:serve with a bundle of its own: built $builtAgo seconds ago (none when null), from code
 * changed $changedAgo seconds ago.
 */
function lanServeWithBundle(string $directory, ?int $builtAgo, int $changedAgo): ServeLanCommand
{
    touch("{$directory}/js/pages/Dashboard.vue", time() - $changedAgo);
    if ($builtAgo !== null) {
        file_put_contents("{$directory}/build/manifest.json", '{}');
        touch("{$directory}/build/manifest.json", time() - $builtAgo);
    }

    return new class($directory) extends ServeLanCommand
    {
        public BufferedOutput $buffer;

        public function __construct(public string $directory)
        {
            parent::__construct();
            $this->buffer = new BufferedOutput;
            $this->setOutput(new OutputStyle(new ArrayInput([]), $this->buffer));
        }

        protected function manifestPath(): string
        {
            return "{$this->directory}/build/manifest.json";
        }

        protected function assetSourcePaths(): array
        {
            return ["{$this->directory}/js"];
        }

        public function buildWhenNeeded(bool $isBuildAsked = false): bool
        {
            return $this->buildAssetsWhenNeeded($isBuildAsked);
        }
    };
}

test('no test builds the pages of this checkout for real', function () {
    $manifest = app(AssetBuild::class)->manifestPath();
    $builtAt = @filemtime($manifest);

    expect(app(AssetBuild::class)->build())->toBeTrue();

    Process::assertRan(fn (PendingProcess $process): bool => $process->command === 'npm run build'
        && $process->path === base_path()
        && $process->timeout === 600);
    clearstatcache();
    expect(@filemtime($manifest))->toBe($builtAt);
});

test('lan:serve builds the pages when the code is newer than the bundle, and only then', function () {
    expect(lanServeWithBundle($this->directory, builtAgo: 60, changedAgo: 120)->buildWhenNeeded())->toBeTrue();
    Process::assertDidntRun(AssetBuild::COMMAND);

    // After git pull or a merge: the code is newer.
    $pulled = lanServeWithBundle($this->directory, builtAgo: 60, changedAgo: 0);

    expect($pulled->buildWhenNeeded())->toBeTrue()
        ->and($pulled->buffer->fetch())->toContain('Kode tampilan lebih baru daripada bundle aset');
    Process::assertRanTimes(AssetBuild::COMMAND, 1);
});

test('lan:serve builds the pages when there are none yet, or when asked with --build', function () {
    $fresh = lanServeWithBundle($this->directory, builtAgo: null, changedAgo: 120);

    expect($fresh->buildWhenNeeded())->toBeTrue()
        ->and($fresh->buffer->fetch())->toContain('Membuat bundle aset produksi');

    expect(lanServeWithBundle($this->directory, builtAgo: 60, changedAgo: 120)->buildWhenNeeded(isBuildAsked: true))->toBeTrue();
    Process::assertRanTimes(AssetBuild::COMMAND, 2);
});

test('a failed build stops lan:serve only when there are no pages at all to serve', function () {
    Process::fake([AssetBuild::COMMAND => Process::result(errorOutput: 'vite: error', exitCode: 1)]);

    $outdated = lanServeWithBundle($this->directory, builtAgo: 60, changedAgo: 0);

    expect($outdated->buildWhenNeeded())->toBeTrue()
        ->and($outdated->buffer->fetch())->toContain('Gagal melakukan build aset.', 'Server tetap berjalan dengan tampilan lama');

    File::delete("{$this->directory}/build/manifest.json");
    $none = lanServeWithBundle($this->directory, builtAgo: null, changedAgo: 0);

    expect($none->buildWhenNeeded())->toBeFalse()
        ->and($none->buffer->fetch())->toContain('Gagal melakukan build aset.')->not->toContain('tampilan lama');
});
