<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The question bank of a teacher: a copy of every question they wrote in any of their exams,
     * kept (also after it is removed from the exam or the exam is deleted) so it can be reused.
     */
    public function up(): void
    {
        Schema::create('question_bank_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quiz_form_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_question_id', 64);
            $table->char('fingerprint', 40);
            $table->string('type', 100);
            $table->text('search_text');
            $table->json('question');
            $table->timestamps();

            $table->unique(['quiz_form_id', 'source_question_id']);
            $table->index(['user_id', 'fingerprint']);
            $table->index(['user_id', 'updated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('question_bank_items');
    }
};
