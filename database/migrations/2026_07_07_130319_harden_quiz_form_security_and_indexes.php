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
            $table->index(['user_id', 'deleted_at', 'updated_at']);
            $table->index(['published_at']);
        });

        Schema::table('unlock_requests', function (Blueprint $table) {
            $table->index(['quiz_form_id', 'status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('unlock_requests', function (Blueprint $table) {
            $table->dropIndex(['quiz_form_id', 'status', 'created_at']);
        });

        Schema::table('quiz_forms', function (Blueprint $table) {
            $table->dropIndex(['published_at']);
            $table->dropIndex(['user_id', 'deleted_at', 'updated_at']);
        });
    }
};
