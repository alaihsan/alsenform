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
            $table->string('folder')->nullable()->after('template');
            $table->softDeletes();

            $table->index(['user_id', 'folder', 'updated_at']);
            $table->index(['user_id', 'deleted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quiz_forms', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'folder', 'updated_at']);
            $table->dropIndex(['user_id', 'deleted_at']);
            $table->dropColumn('folder');
            $table->dropSoftDeletes();
        });
    }
};
