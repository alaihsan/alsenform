<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('quiz_responses', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('quiz_form_id')->constrained()->nullOnDelete();
            $table->unsignedInteger('score')->nullable()->after('answers');
            $table->boolean('is_timeout')->default(false)->after('score');

            $table->index(['quiz_form_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quiz_responses', function (Blueprint $table) {
            $table->dropIndex(['quiz_form_id', 'user_id']);
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['score', 'is_timeout']);
        });
    }
};
