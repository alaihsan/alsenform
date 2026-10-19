<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The footer (Dikembangkan oleh Falcon Education Partner, licence, privacy) is on every page for
 * teachers and admins. Students and visitors on the sign-in page do not get it; the page decides
 * from auth.isStudent, which follows User::isStudent (also for old roles and accounts with a NIS).
 */
test('teachers and admins are told apart from students for the footer', function (array $attributes, bool $isStudent) {
    $this->actingAs(User::factory()->create($attributes))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.isStudent', $isStudent));
})->with([
    'guru' => [['role' => 'guru'], false],
    'admin' => [['role' => 'admin', 'is_admin' => true], false],
    'siswa' => [['role' => 'siswa', 'nis' => '880021'], true],
    'akun lama "murid"' => [['role' => 'murid', 'nis' => '880022'], true],
]);

test('the sign-in page has no signed-in user, so no footer', function () {
    $this->get('/')
        ->assertInertia(fn (Assert $page) => $page->component('Welcome')->where('auth.user', null)->where('auth.isStudent', false));
});
