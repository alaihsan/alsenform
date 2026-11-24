<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The length in seconds of a spoken answer (Jawaban Suara), shown without loading the sound.
     */
    public function up(): void
    {
        Schema::table('answer_files', function (Blueprint $table) {
            $table->decimal('duration', 8, 2)->nullable()->after('size');
        });
    }

    public function down(): void
    {
        Schema::table('answer_files', function (Blueprint $table) {
            $table->dropColumn('duration');
        });
    }
};
