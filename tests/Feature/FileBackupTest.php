<?php

use App\Console\Commands\ServeLanCommand;
use App\Models\User;
use App\Services\FileBackup;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/*
 * files:backup: the files of the exams (media of the questions, students' uploads and recordings)
 * copied next to the database copies, only what is new or changed, made by lan:serve every day.
 */

beforeEach(function () {
    $this->backups = storage_path('framework/testing/file-backups-'.uniqid());
    config(['maintenance.backup.path' => $this->backups]);
    Storage::fake('public');
    Storage::fake('local');
    $this->mirror = $this->backups.'/berkas';
});

afterEach(function () {
    File::deleteDirectory($this->backups);
    Carbon::setTestNow();
});

test('the media of the questions and what students handed in are copied, with how to restore them', function () {
    Storage::disk('public')->put('media/audio/listening.mp3', 'mp3-bytes');
    Storage::disk('public')->put('media/images/peta.png', 'png-bytes');
    Storage::disk('local')->put('answer-files/7/12/rekaman.mp3', 'spoken-answer');
    Storage::disk('local')->put('.gitignore', "*\n");

    $this->artisan('files:backup')
        ->expectsOutputToContain('Backup berkas tersimpan di '.substr($this->mirror, strlen(base_path()) + 1).': 3 berkas (1 KB), 3 baru atau berubah disalin (1 KB).')
        ->expectsOutputToContain('Untuk memulihkan: Salin isi '.$this->mirror.'/public ke '.rtrim(Storage::disk('public')->path(''), '/')
            .', lalu salin isi '.$this->mirror.'/private ke '.rtrim(Storage::disk('local')->path(''), '/').'.')
        ->assertSuccessful();

    expect(File::get($this->mirror.'/public/media/audio/listening.mp3'))->toBe('mp3-bytes')
        ->and(File::get($this->mirror.'/public/media/images/peta.png'))->toBe('png-bytes')
        ->and(File::get($this->mirror.'/private/answer-files/7/12/rekaman.mp3'))->toBe('spoken-answer')
        ->and(File::exists($this->mirror.'/private/.gitignore'))->toBeFalse()
        // Students' answers: for the server's own account only.
        ->and(fileperms($this->mirror.'/private/answer-files/7/12/rekaman.mp3') & 0777)->toBe(0640)
        ->and(File::glob($this->mirror.'/**/*.partial'))->toBe([]);
});

test('only new and changed files are copied again, and files gone from the server stay in the backup', function () {
    Storage::disk('public')->put('media/audio/a.mp3', 'first');
    Storage::disk('local')->put('answer-files/1/1/b.pdf', 'pdf');
    app(FileBackup::class)->run();

    // Nothing new: nothing copied.
    $again = app(FileBackup::class)->run();
    expect($again)->toMatchArray(['files' => 2, 'copied' => 0, 'copiedBytes' => 0]);

    // A replaced sound, a new recording, and an answer file removed from the server.
    $path = Storage::disk('public')->path('media/audio/a.mp3');
    File::put($path, 'second version');
    touch($path, time() + 5);
    Storage::disk('local')->put('answer-files/1/2/c.mp3', 'new');
    Storage::disk('local')->delete('answer-files/1/1/b.pdf');

    $made = app(FileBackup::class)->run();

    expect($made)->toMatchArray(['files' => 2, 'copied' => 2])
        ->and(File::get($this->mirror.'/public/media/audio/a.mp3'))->toBe('second version')
        ->and(File::get($this->mirror.'/private/answer-files/1/2/c.mp3'))->toBe('new')
        // A disk that lost its files must not empty the backup too.
        ->and(File::get($this->mirror.'/private/answer-files/1/1/b.pdf'))->toBe('pdf');
});

test('the last run is remembered, and is old after a day', function () {
    expect(app(FileBackup::class)->latest())->toBeNull()
        ->and(app(FileBackup::class)->isOlderThan(24))->toBeTrue();

    Carbon::setTestNow('2026-11-25 01:15:00');
    Storage::disk('public')->put('avatars/guru.jpg', str_repeat('x', 2048));
    app(FileBackup::class)->run();

    expect(app(FileBackup::class)->latest())->toMatchArray(['files' => 1, 'bytes' => 2048])
        ->and(app(FileBackup::class)->latest()['finishedAt']->equalTo(now()))->toBeTrue()
        ->and(app(FileBackup::class)->isOlderThan(24))->toBeFalse();

    Carbon::setTestNow('2026-11-26 02:00:00');
    expect(app(FileBackup::class)->isOlderThan(24))->toBeTrue();
});

test('a disk without room says so and copies nothing', function () {
    app()->bind(FileBackup::class, fn () => new class extends FileBackup
    {
        protected function freeSpace(string $directory): ?int
        {
            return 1024;
        }
    });
    Storage::disk('local')->put('answer-files/1/1/rekaman.mp3', 'spoken-answer');

    $this->artisan('files:backup')
        ->expectsOutputToContain('Backup berkas gagal: Ruang disk untuk backup berkas tidak cukup: perlu 1 KB, tersisa 1 KB. Kosongkan disk, atau arahkan BACKUP_PATH di .env ke disk lain.')
        ->assertFailed();

    expect(File::exists($this->mirror.'/private/answer-files/1/1/rekaman.mp3'))->toBeFalse()
        ->and(app(FileBackup::class)->latest())->toBeNull();
});

test('lan:serve copies the files with the day\'s database backup, and the scheduler does at night', function () {
    $command = new class extends ServeLanCommand
    {
        public function maintain(float $hours): void
        {
            $this->runDailyMaintenance($hours);
        }
    };
    $output = new BufferedOutput;
    $command->setOutput(new OutputStyle(new ArrayInput([]), $output));
    Storage::disk('local')->put('answer-files/3/4/rekaman.mp3', 'spoken-answer');

    $command->maintain(12);

    expect($output->fetch())->toContain('Backup berkas tersimpan')
        ->and(File::get($this->mirror.'/private/answer-files/3/4/rekaman.mp3'))->toBe('spoken-answer');

    Artisan::call('schedule:list');
    expect(Artisan::output())->toContain('files:backup');
});

test('Kesehatan Server shows the file backup', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
    $check = fn () => collect($this->actingAs($admin)->get(route('server-health.index'))->inertiaProps('health.checks'))->firstWhere('key', 'file_backup');

    expect($check())->toMatchArray(['label' => 'Backup berkas', 'value' => 'Belum ada', 'tone' => 'bad']);

    Storage::disk('public')->put('media/audio/a.mp3', str_repeat('x', 1536));
    app(FileBackup::class)->run();

    expect($check())->toMatchArray(['label' => 'Backup berkas', 'tone' => 'good', 'detail' => '1 berkas · 1,5 KB']);
});
