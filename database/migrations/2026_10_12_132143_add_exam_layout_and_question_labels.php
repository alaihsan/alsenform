<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * - quiz_sessions.layout: the questions a student gets (a random draw or all) in their order,
     *   and the order of the choices, fixed once per attempt. last_seen_at: when the exam page last
     *   reached the server, to tell a page that was offline at the end from one that kept going.
     * - quiz_responses.question_ids: the questions of that attempt (graded out of those only);
     *   late_seconds: answers that arrived after the time ran out.
     * - question_bank_items: labels to find and draw questions by, and an archive.
     */
    public function up(): void
    {
        Schema::table('quiz_sessions', function (Blueprint $table) {
            $table->json('layout')->nullable()->after('draft_saved_at');
            $table->timestamp('last_seen_at')->nullable()->after('layout');
        });

        Schema::table('quiz_responses', function (Blueprint $table) {
            $table->json('question_ids')->nullable()->after('answers');
            $table->unsignedInteger('late_seconds')->nullable()->after('is_auto_submitted');
        });

        Schema::table('question_bank_items', function (Blueprint $table) {
            $table->string('subject', 100)->nullable()->after('type');
            $table->string('grade', 50)->nullable()->after('subject');
            $table->string('topic', 150)->nullable()->after('grade');
            $table->string('difficulty', 10)->nullable()->after('topic');
            $table->timestamp('archived_at')->nullable()->after('question');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('question_bank_items', function (Blueprint $table) {
            $table->dropColumn(['subject', 'grade', 'topic', 'difficulty', 'archived_at']);
        });

        Schema::table('quiz_responses', function (Blueprint $table) {
            $table->dropColumn(['question_ids', 'late_seconds']);
        });

        Schema::table('quiz_sessions', function (Blueprint $table) {
            $table->dropColumn(['layout', 'last_seen_at']);
        });
    }
};
