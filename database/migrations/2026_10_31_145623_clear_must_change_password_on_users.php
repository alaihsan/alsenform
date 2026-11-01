<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Accounts no longer have to change their default password on the first login. Accounts made
 * or reset before that still carry the flag: it is cleared, so no one is kept waiting on the
 * "Aktivasi Akun" page.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('must_change_password', true)->update(['must_change_password' => false]);
    }

    /**
     * Which accounts carried the flag is not kept.
     */
    public function down(): void {}
};
