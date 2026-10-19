<?php

use App\Support\QuizItems;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Forms made from the blank template before the interface was Indonesian still carry its
 * English placeholders, which students saw: "Untitled form", "Form description" and
 * "Untitled Question". They become the Indonesian ones (the description becomes empty).
 * Option texts stay as they are: answers are stored by their text.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('quiz_forms')->orderBy('id')->chunkById(200, function ($forms): void {
            foreach ($forms as $form) {
                $changes = [];

                if ($form->title === 'Untitled form') {
                    $changes['title'] = 'Formulir tanpa judul';
                }

                if ($form->description === 'Form description') {
                    $changes['description'] = '';
                }

                $items = json_decode((string) $form->questions, true);
                if (is_array($items)) {
                    $renamed = false;
                    foreach ($items as $index => $item) {
                        if (QuizItems::isQuestion($item) && ($item['title'] ?? null) === 'Untitled Question') {
                            $items[$index]['title'] = QuizItems::UNTITLED_QUESTION;
                            $renamed = true;
                        }
                    }
                    if ($renamed) {
                        $changes['questions'] = json_encode($items);
                    }
                }

                if ($changes !== []) {
                    DB::table('quiz_forms')->where('id', $form->id)->update($changes);
                }
            }
        });
    }

    /**
     * The English placeholders are not put back.
     */
    public function down(): void {}
};
