<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Grading by hand: the points the teacher gave per question (essays, files, short answers the
     * key judged too strictly) and a comment per question for the student. And the files students
     * hand in for "File upload" questions.
     */
    public function up(): void
    {
        Schema::table('quiz_responses', function (Blueprint $table) {
            $table->json('manual_scores')->nullable()->after('score');
            $table->json('feedback')->nullable()->after('manual_scores');
        });

        Schema::create('answer_files', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('quiz_form_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quiz_session_id')->constrained()->cascadeOnDelete();
            $table->string('question_id', 64);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size');
            $table->timestamps();

            $table->index(['quiz_session_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('answer_files');

        Schema::table('quiz_responses', function (Blueprint $table) {
            $table->dropColumn(['manual_scores', 'feedback']);
        });
    }
};
