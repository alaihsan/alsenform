<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * When the time of an exam runs out while the page is closed or offline, the server hands the
     * answers saved so far in by itself. The response remembers its exam attempt and that it was
     * handed in by the server, so the page can still send its own (newer) answers afterwards.
     */
    public function up(): void
    {
        Schema::table('quiz_responses', function (Blueprint $table) {
            $table->foreignId('quiz_session_id')->nullable()->after('user_id')->constrained('quiz_sessions')->nullOnDelete();
            $table->boolean('is_auto_submitted')->default(false)->after('is_timeout');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quiz_responses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('quiz_session_id');
            $table->dropColumn('is_auto_submitted');
        });
    }
};
