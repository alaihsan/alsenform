<?php

namespace Tests;

use App\Support\AssetBuild;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Process;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The tests may run in the checkout the school's server serves. lan:serve and exam:optimize
        // build the pages again when the code is newer than the bundle, and a real build would
        // empty public/build under the students' open exams; mail:gmail caches the configuration
        // again, which would give the server the tests' settings. In tests both are only pretended.
        Process::fake([
            AssetBuild::COMMAND => Process::result('vite build (faked in tests)'),
            "*'artisan' 'config:cache'" => Process::result('config cached (faked in tests)'),
        ]);
    }
}
