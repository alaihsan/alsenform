<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether the answers given reach the server, and proof of what was handed in:
     * - per attempt, since when the device holds answers the server does not have yet (for the
     *   proctor's Peta Kelas) and the longest it took a change to reach the server;
     * - per response, how many tries handing in took and the receipt (a hash of the answers);
     * - copies of the answers on the server every few minutes, for "my answers are gone".
     */
    public function up(): void
    {
        Schema::table('quiz_sessions', function (Blueprint $table) {
            $table->timestamp('unsynced_since')->nullable()->after('draft_saved_at');
            $table->unsignedInteger('max_sync_delay_seconds')->nullable()->after('unsynced_since');
        });

        Schema::table('quiz_responses', function (Blueprint $table) {
            $table->unsignedSmallInteger('submit_attempts')->nullable()->after('late_seconds');
            $table->string('receipt_hash', 64)->nullable()->after('submit_attempts');
        });

        Schema::create('quiz_session_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_session_id')->constrained()->cascadeOnDelete();
            $table->json('answers');
            $table->unsignedSmallInteger('answered')->default(0);
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['quiz_session_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_session_snapshots');

        Schema::table('quiz_responses', function (Blueprint $table) {
            $table->dropColumn(['submit_attempts', 'receipt_hash']);
        });

        Schema::table('quiz_sessions', function (Blueprint $table) {
            $table->dropColumn(['unsynced_since', 'max_sync_delay_seconds']);
        });
    }
};
