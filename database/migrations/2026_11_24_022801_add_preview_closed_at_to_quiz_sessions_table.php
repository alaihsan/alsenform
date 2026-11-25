<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tutup pratinjau: when the teacher closed their preview of the exam. With the time the
     * preview was opened (created_at) it tells how long they looked at it.
     */
    public function up(): void
    {
        Schema::table('quiz_sessions', function (Blueprint $table) {
            $table->timestamp('preview_closed_at')->nullable()->after('submitted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quiz_sessions', function (Blueprint $table) {
            $table->dropColumn('preview_closed_at');
        });
    }
};
