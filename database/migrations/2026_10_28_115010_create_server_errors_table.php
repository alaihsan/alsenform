<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Errors of the server (HTTP 500), grouped: the same fault counted once with how often and when
     * it happened, instead of only lines in storage/logs/laravel.log. Every occurrence is also in
     * activity_logs (event server_error) with its reference code, account and device.
     */
    public function up(): void
    {
        Schema::create('server_errors', function (Blueprint $table) {
            $table->id();
            $table->string('fingerprint', 40)->unique();
            $table->string('exception');
            $table->text('message');
            $table->string('location')->nullable();
            $table->unsignedInteger('occurrences')->default(0);
            $table->string('last_reference', 16)->nullable();
            $table->string('last_method', 10)->nullable();
            $table->text('last_url')->nullable();
            $table->foreignId('last_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('server_errors');
    }
};
