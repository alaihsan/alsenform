<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A session is opened when the exam page loads, but the exam (and its timer) only
     * starts once the student confirms "Kerjakan Sekarang", so started_at may be empty.
     */
    public function up(): void
    {
        Schema::table('quiz_sessions', function (Blueprint $table) {
            $table->timestamp('started_at')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('quiz_sessions')->whereNull('started_at')->update(['started_at' => DB::raw('created_at')]);

        Schema::table('quiz_sessions', function (Blueprint $table) {
            $table->timestamp('started_at')->nullable(false)->change();
        });
    }
};
