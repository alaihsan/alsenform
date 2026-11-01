<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A form description holds up to 2000 characters of text, plus its bold, italic, underline
     * and highlighter tags: more than a varchar(255) holds.
     */
    public function up(): void
    {
        Schema::table('quiz_forms', function (Blueprint $table) {
            $table->text('description')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * Longer descriptions are cut to 255 characters first, as the old column holds no more.
     */
    public function down(): void
    {
        DB::table('quiz_forms')->whereNotNull('description')->orderBy('id')->chunkById(200, function ($forms): void {
            foreach ($forms as $form) {
                if (mb_strlen($form->description) > 255) {
                    DB::table('quiz_forms')->where('id', $form->id)->update(['description' => mb_substr($form->description, 0, 255)]);
                }
            }
        });

        Schema::table('quiz_forms', function (Blueprint $table) {
            $table->string('description')->nullable()->change();
        });
    }
};
