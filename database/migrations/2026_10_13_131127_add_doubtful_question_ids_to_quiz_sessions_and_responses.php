<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The questions a student marked "Ragu-ragu" (not sure): kept with the draft of the attempt and
     * with the answers handed in. Null on answers handed in before the button existed.
     */
    public function up(): void
    {
        Schema::table('quiz_sessions', function (Blueprint $table) {
            $table->json('doubtful_question_ids')->nullable()->after('draft_answers');
        });

        Schema::table('quiz_responses', function (Blueprint $table) {
            $table->json('doubtful_question_ids')->nullable()->after('answers');
        });
    }

    public function down(): void
    {
        Schema::table('quiz_sessions', function (Blueprint $table) {
            $table->dropColumn('doubtful_question_ids');
        });

        Schema::table('quiz_responses', function (Blueprint $table) {
            $table->dropColumn('doubtful_question_ids');
        });
    }
};
