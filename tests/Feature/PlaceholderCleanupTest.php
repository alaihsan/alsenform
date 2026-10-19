<?php

use App\Models\QuizForm;
use App\Services\QuestionBank;
use App\Support\QuizItems;

/*
 * Forms made from the blank template before the interface was Indonesian still carried its
 * English placeholders, and students saw them. A migration replaces them once; the editor and
 * the question bank recognise every placeholder, in any case.
 */
test('old english placeholders become indonesian, options stay as they are', function () {
    $old = QuizForm::factory()->create([
        'title' => 'Untitled form',
        'description' => 'Form description',
        'questions' => [
            ['id' => 1, 'title' => 'Untitled Question', 'type' => 'Multiple choice', 'options' => ['Option 1', 'Option 2'], 'answer' => 'Option 1', 'points' => 1],
            ['id' => 2, 'title' => 'Ibu kota Indonesia?', 'type' => 'Short answer', 'points' => 1],
            ['id' => 3, 'title' => 'Untitled Question', 'description' => '', 'type' => QuizItems::SECTION],
        ],
    ]);
    $written = QuizForm::factory()->create(['title' => 'Ulangan Harian', 'description' => 'Bab 1']);

    (require base_path('database/migrations/2026_10_19_120704_replace_english_placeholders_in_quiz_forms.php'))->up();

    $old->refresh();
    expect($old->title)->toBe('Formulir tanpa judul')
        ->and($old->description)->toBe('')
        ->and($old->questions[0]['title'])->toBe(QuizItems::UNTITLED_QUESTION)
        // Answers are stored by the option text: options keep theirs.
        ->and($old->questions[0]['options'])->toBe(['Option 1', 'Option 2'])
        ->and($old->questions[1]['title'])->toBe('Ibu kota Indonesia?')
        // A section is not a question: its title is the teacher's.
        ->and($old->questions[2]['title'])->toBe('Untitled Question')
        ->and($written->fresh()->only(['title', 'description']))->toBe(['title' => 'Ulangan Harian', 'description' => 'Bab 1']);
});

test('a placeholder title is recognised whatever its case', function () {
    expect(QuizItems::isPlaceholderTitle('Pertanyaan tanpa judul'))->toBeTrue()
        ->and(QuizItems::isPlaceholderTitle('untitled question'))->toBeTrue()
        ->and(QuizItems::isPlaceholderTitle('  SHORT ANSWER QUESTION '))->toBeTrue()
        ->and(QuizItems::isPlaceholderTitle('Pertanyaan tentang judul buku'))->toBeFalse();

    // The question bank skips placeholders, in any case.
    $isWritten = (fn (array $question): bool => $this->isWritten($question))->call(app(QuestionBank::class), ['title' => 'UNTITLED QUESTION', 'type' => 'Short answer']);
    expect($isWritten)->toBeFalse();
});
