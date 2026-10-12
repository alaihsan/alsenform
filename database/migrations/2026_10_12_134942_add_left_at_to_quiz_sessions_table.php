<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * When the exam page is closed or reloaded (with the tab-switch lock on), the moment is noted:
     * back within a few seconds is a reload and nothing happens, away longer locks the attempt.
     */
    public function up(): void
    {
        Schema::table('quiz_sessions', function (Blueprint $table) {
            $table->timestamp('left_at')->nullable()->after('last_seen_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quiz_sessions', function (Blueprint $table) {
            $table->dropColumn('left_at');
        });
    }
};
