<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unlock_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_form_id')->constrained()->cascadeOnDelete();
            $table->string('respondent_identifier'); // Random token generated in browser
            $table->string('email')->nullable();
            $table->string('unlock_code');
            $table->string('status')->default('pending'); // pending, approved
            $table->timestamps();

            $table->unique(['quiz_form_id', 'respondent_identifier']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unlock_requests');
    }
};
