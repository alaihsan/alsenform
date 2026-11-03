<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Mode pewawancara, "Skor diisi pewawancara": the score of each choice is its maximum, and the
     * interviewer types the score the candidate earns (0 up to it, decimals allowed) for every
     * choice made. The attempt keeps them while it is filled; the response keeps them by question
     * and choice, while their sum per question counts like a grade given by hand.
     */
    public function up(): void
    {
        Schema::table('quiz_sessions', function (Blueprint $table) {
            $table->json('draft_scores')->nullable()->after('draft_answers');
        });

        Schema::table('quiz_responses', function (Blueprint $table) {
            $table->json('entered_scores')->nullable()->after('manual_scores');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quiz_responses', function (Blueprint $table) {
            $table->dropColumn('entered_scores');
        });

        Schema::table('quiz_sessions', function (Blueprint $table) {
            $table->dropColumn('draft_scores');
        });
    }
};
