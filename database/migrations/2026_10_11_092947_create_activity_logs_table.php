<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Logins, logouts and exam participation with the device, browser and time they happened,
     * for the Laporan (report) page.
     */
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event', 32);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('quiz_form_id')->nullable()->constrained()->nullOnDelete();
            // Respondents without an account (public exams): their email when known.
            $table->string('respondent_identifier', 100)->nullable();
            $table->string('respondent_email')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device_type', 16)->default('unknown');
            $table->string('device')->nullable();
            $table->string('os', 100)->nullable();
            $table->string('browser', 100)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['event', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
