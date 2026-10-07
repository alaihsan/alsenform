<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * New questions are now worth 1 point by default. Exams saved earlier store
     * "defaultQuestionPoints: 10" only because that was the editor's built-in value (adding a
     * question ignored the setting until now), so it is reset to 1. Points already given to
     * questions are left untouched.
     */
    public function up(): void
    {
        DB::table('quiz_forms')->select(['id', 'settings'])->orderBy('id')->chunkById(200, function ($forms): void {
            foreach ($forms as $form) {
                $settings = json_decode((string) $form->settings, true);
                if (! is_array($settings) || (int) ($settings['defaultQuestionPoints'] ?? 0) !== 10) {
                    continue;
                }

                $settings['defaultQuestionPoints'] = 1;
                DB::table('quiz_forms')->where('id', $form->id)->update(['settings' => json_encode($settings)]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // The previous value cannot be told apart from a teacher's own choice; nothing to undo.
    }
};
