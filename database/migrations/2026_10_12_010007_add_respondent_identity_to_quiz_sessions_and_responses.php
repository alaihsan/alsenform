<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Guests on a public quiz (no account) give their name, class and email on the confirmation
     * screen. The exam session keeps them from the start, the response and the activity log
     * keep a copy.
     */
    public function up(): void
    {
        Schema::table('quiz_sessions', function (Blueprint $table) {
            $table->string('respondent_name', 100)->nullable()->after('respondent_identifier');
            $table->string('respondent_class', 20)->nullable()->after('respondent_name');
            $table->string('respondent_email')->nullable()->after('respondent_class');
        });

        Schema::table('quiz_responses', function (Blueprint $table) {
            $table->string('respondent_name', 100)->nullable()->after('respondent_identifier');
            $table->string('respondent_class', 20)->nullable()->after('respondent_name');
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->string('respondent_name', 100)->nullable()->after('respondent_email');
            $table->string('respondent_class', 20)->nullable()->after('respondent_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropColumn(['respondent_name', 'respondent_class']);
        });

        Schema::table('quiz_responses', function (Blueprint $table) {
            $table->dropColumn(['respondent_name', 'respondent_class']);
        });

        Schema::table('quiz_sessions', function (Blueprint $table) {
            $table->dropColumn(['respondent_name', 'respondent_class', 'respondent_email']);
        });
    }
};
