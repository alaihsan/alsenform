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
        Schema::table('quiz_forms', function (Blueprint $table) {
            $table->foreignId('quiz_folder_id')
                ->nullable()
                ->after('folder')
                ->constrained()
                ->nullOnDelete();

            $table->index(['user_id', 'quiz_folder_id', 'updated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quiz_forms', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'quiz_folder_id', 'updated_at']);
            $table->dropConstrainedForeignId('quiz_folder_id');
        });
    }
};
