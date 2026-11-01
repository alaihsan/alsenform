<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Mode pewawancara: an interviewer fills a scored questionnaire for each candidate in turn,
     * giving the candidate's name, registration number and school. The response says it was
     * filled for that candidate, so it is the candidate's and not the interviewer's.
     */
    public function up(): void
    {
        Schema::table('quiz_sessions', function (Blueprint $table) {
            $table->string('respondent_number', 50)->nullable()->after('respondent_class');
            $table->string('respondent_school', 150)->nullable()->after('respondent_number');
        });

        Schema::table('quiz_responses', function (Blueprint $table) {
            $table->string('respondent_number', 50)->nullable()->after('respondent_class');
            $table->string('respondent_school', 150)->nullable()->after('respondent_number');
            $table->boolean('filled_for_respondent')->default(false)->after('respondent_school');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quiz_responses', function (Blueprint $table) {
            $table->dropColumn(['respondent_number', 'respondent_school', 'filled_for_respondent']);
        });

        Schema::table('quiz_sessions', function (Blueprint $table) {
            $table->dropColumn(['respondent_number', 'respondent_school']);
        });
    }
};
