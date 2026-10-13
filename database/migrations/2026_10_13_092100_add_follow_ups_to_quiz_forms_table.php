<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remedial & Pengayaan add-on: a follow-up exam (remedial or enrichment) points to the exam it
     * follows up, and is taken only by the students chosen for it.
     */
    public function up(): void
    {
        Schema::table('quiz_forms', function (Blueprint $table) {
            $table->foreignId('follow_up_of_id')->nullable()->after('user_id')->constrained('quiz_forms')->nullOnDelete();
            $table->string('follow_up_kind', 20)->nullable()->after('follow_up_of_id');
        });

        Schema::create('quiz_form_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_form_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['quiz_form_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_form_participants');

        Schema::table('quiz_forms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('follow_up_of_id');
            $table->dropColumn('follow_up_kind');
        });
    }
};
