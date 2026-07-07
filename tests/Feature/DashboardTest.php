<?php

use App\Models\QuizFolder;
use App\Models\QuizForm;
use App\Models\QuizResponse;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get('/dashboard');
    $response->assertRedirect('/login');
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get('/dashboard');
    $response
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('recentForms', 0)
        );
});

test('dashboard shows recent forms sorted by last update', function () {
    $user = User::factory()->create();
    $olderForm = QuizForm::factory()->for($user)->create([
        'title' => 'Older quiz',
        'slug' => 'older-quiz',
        'updated_at' => now()->subDay(),
    ]);
    $newerForm = QuizForm::factory()->for($user)->create([
        'title' => 'Newer quiz',
        'slug' => 'newer-quiz',
        'updated_at' => now(),
    ]);

    $this->actingAs($user);

    $response = $this->get('/dashboard');

    expect($response->inertiaProps('recentForms.0.id'))->toBe($newerForm->id)
        ->and($response->inertiaProps('recentForms.1.id'))->toBe($olderForm->id);
});

test('dashboard includes folder and trash metadata', function () {
    $user = User::factory()->create();
    $folder = QuizFolder::factory()->for($user)->create([
        'name' => 'Client Work',
    ]);
    $activeForm = QuizForm::factory()->for($user)->create([
        'quiz_folder_id' => $folder->id,
        'folder' => 'Client Work',
        'updated_at' => now(),
    ]);
    $trashedForm = QuizForm::factory()->for($user)->create([
        'updated_at' => now()->subMinute(),
    ]);
    $trashedForm->delete();

    $this->actingAs($user);

    $response = $this->get('/dashboard');

    $recentForms = collect($response->inertiaProps('recentForms'));
    $activePayload = $recentForms->firstWhere('id', $activeForm->id);
    $trashedPayload = $recentForms->firstWhere('id', $trashedForm->id);

    expect($activePayload['folder'])->toBe('Client Work')
        ->and($activePayload['folderId'])->toBe($folder->id)
        ->and($activePayload['isTrashed'])->toBeFalse()
        ->and($trashedPayload['isTrashed'])->toBeTrue();
});

test('authenticated users can create and open the form editor', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $createResponse = $this->get('/forms/create/blank');
    $quizForm = QuizForm::query()->whereBelongsTo($user)->firstOrFail();

    $createResponse->assertRedirect(route('forms.edit', ['quizForm' => $quizForm->slug]));

    $response = $this->get(route('forms.edit', ['quizForm' => $quizForm->slug]));
    $response
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('FormEditor')
            ->where('template', 'blank')
            ->where('quizForm.id', $quizForm->id)
        );
});

test('authenticated users can duplicate their own form', function () {
    $user = User::factory()->create();
    $quizForm = QuizForm::factory()->for($user)->create([
        'title' => 'Customer survey',
        'published_at' => now(),
    ]);

    $this->actingAs($user);

    $response = $this->post(route('forms.duplicate', $quizForm));

    $duplicate = QuizForm::query()
        ->whereBelongsTo($user)
        ->where('title', 'Customer survey (Copy)')
        ->firstOrFail();

    $response->assertRedirect(route('forms.edit', ['quizForm' => $duplicate->slug]));
    expect($duplicate->slug)->not->toBe($quizForm->slug)
        ->and($duplicate->published_at)->toBeNull()
        ->and($duplicate->questions)->toBe($quizForm->questions)
        ->and($duplicate->settings)->toBe($quizForm->settings);
});

test('authenticated users can delete their own form', function () {
    $user = User::factory()->create();
    $quizForm = QuizForm::factory()->for($user)->create();

    $this->actingAs($user);

    $this->delete(route('forms.destroy', $quizForm))
        ->assertRedirect(route('dashboard'));

    $this->assertSoftDeleted($quizForm);
});

test('authenticated users can restore and permanently delete trashed forms', function () {
    $user = User::factory()->create();
    $quizForm = QuizForm::factory()->for($user)->create();
    $quizForm->delete();

    $this->actingAs($user);

    $this->patch(route('forms.restore', $quizForm))
        ->assertRedirect(route('dashboard'));

    $this->assertNotSoftDeleted($quizForm);

    $quizForm->delete();

    $this->delete(route('forms.force-delete', $quizForm))
        ->assertRedirect(route('dashboard'));

    $this->assertDatabaseMissing('quiz_forms', [
        'id' => $quizForm->id,
    ]);
});

test('authenticated users can move forms to a folder', function () {
    $user = User::factory()->create();
    $quizForm = QuizForm::factory()->for($user)->create();
    $folder = QuizFolder::factory()->for($user)->create([
        'name' => 'Client Work',
    ]);

    $this->actingAs($user);

    $this->patch(route('forms.folder', $quizForm), [
        'folder_id' => $folder->id,
    ])->assertRedirect(route('dashboard'));

    expect($quizForm->refresh()->folder)->toBe('Client Work')
        ->and($quizForm->quiz_folder_id)->toBe($folder->id);
});

test('authenticated users can remove forms from a folder', function () {
    $user = User::factory()->create();
    $folder = QuizFolder::factory()->for($user)->create([
        'name' => 'Client Work',
    ]);
    $quizForm = QuizForm::factory()->for($user)->for($folder)->create([
        'folder' => 'Client Work',
    ]);

    $this->actingAs($user);

    $this->patch(route('forms.folder', $quizForm), [
        'folder_id' => null,
    ])->assertRedirect(route('dashboard'));

    expect($quizForm->refresh()->folder)->toBeNull()
        ->and($quizForm->quiz_folder_id)->toBeNull();
});

test('authenticated users can create a folder', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $this->post(route('folders.store'), [
        'name' => 'Client Work',
    ])->assertRedirect(route('dashboard'));

    $this->assertDatabaseHas('quiz_folders', [
        'user_id' => $user->id,
        'name' => 'Client Work',
    ]);
});

test('authenticated users can rename a folder', function () {
    $user = User::factory()->create();
    $folder = QuizFolder::factory()->for($user)->create([
        'name' => 'Old Folder',
    ]);
    $quizForm = QuizForm::factory()->for($user)->for($folder)->create([
        'folder' => 'Old Folder',
    ]);

    $this->actingAs($user);

    $this->patch(route('folders.update', $folder), [
        'name' => 'New Folder',
    ])->assertRedirect(route('dashboard'));

    expect($folder->refresh()->name)->toBe('New Folder')
        ->and($quizForm->refresh()->folder)->toBe('New Folder');
});

test('authenticated users can delete a folder without deleting its forms', function () {
    $user = User::factory()->create();
    $folder = QuizFolder::factory()->for($user)->create([
        'name' => 'Client Work',
    ]);
    $quizForm = QuizForm::factory()->for($user)->for($folder)->create([
        'folder' => 'Client Work',
    ]);

    $this->actingAs($user);

    $this->delete(route('folders.destroy', $folder))
        ->assertRedirect(route('dashboard'));

    $this->assertDatabaseMissing('quiz_folders', [
        'id' => $folder->id,
    ]);
    expect($quizForm->refresh()->quiz_folder_id)->toBeNull()
        ->and($quizForm->folder)->toBeNull();
});

test('users cannot rename or delete another users folder', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $folder = QuizFolder::factory()->for($owner)->create([
        'name' => 'Owner Folder',
    ]);

    $this->actingAs($otherUser);

    $this->patch(route('folders.update', $folder), ['name' => 'Changed'])->assertForbidden();
    $this->delete(route('folders.destroy', $folder))->assertForbidden();
    $this->assertModelExists($folder);
});

test('users cannot duplicate or delete another users form', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $quizForm = QuizForm::factory()->for($owner)->create();

    $this->actingAs($otherUser);

    $this->post(route('forms.duplicate', $quizForm))->assertForbidden();
    $this->patch(route('forms.folder', $quizForm), ['folder' => 'Other'])->assertForbidden();
    $this->delete(route('forms.destroy', $quizForm))->assertForbidden();
    $this->assertModelExists($quizForm);
});

test('users cannot restore or permanently delete another users trashed form', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $quizForm = QuizForm::factory()->for($owner)->create();
    $quizForm->delete();

    $this->actingAs($otherUser);

    $this->patch(route('forms.restore', $quizForm))->assertForbidden();
    $this->delete(route('forms.force-delete', $quizForm))->assertForbidden();
    $this->assertSoftDeleted($quizForm);
});

test('public quiz link works by unique slug', function () {
    $quizForm = QuizForm::factory()->create([
        'title' => 'Public quiz',
        'slug' => 'public-quiz',
        'published_at' => now(),
    ]);

    $this->get('/forms/public-quiz')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('PublicQuiz')
            ->where('quizForm.title', $quizForm->title)
        );
});

test('public users can submit quiz responses', function () {
    $quizForm = QuizForm::factory()->create([
        'slug' => 'public-response-quiz',
        'published_at' => now(),
        'questions' => [
            [
                'id' => 1,
                'title' => 'Favorite color?',
                'description' => '',
                'type' => 'Multiple choice',
                'options' => ['Blue', 'Green'],
                'answer' => '',
                'required' => true,
                'media' => [],
                'points' => 10,
            ],
        ],
        'settings' => [
            'collectEmail' => true,
            'showProgress' => true,
            'shuffleQuestions' => false,
        ],
    ]);

    $this->post(route('forms.responses.store', ['quizForm' => $quizForm->slug]), [
        'email' => 'respondent@example.com',
        'answers' => [
            1 => 'Blue',
        ],
    ])->assertRedirect(route('forms.public', ['quizForm' => $quizForm->slug]));

    $this->assertDatabaseHas('quiz_responses', [
        'quiz_form_id' => $quizForm->id,
        'email' => 'respondent@example.com',
    ]);
});

test('unpublished public quiz links are not visible to guests', function () {
    $quizForm = QuizForm::factory()->create([
        'slug' => 'draft-public-quiz',
        'published_at' => null,
    ]);

    $this->get(route('forms.public', ['quizForm' => $quizForm->slug]))
        ->assertNotFound();
});

test('form editor includes response summaries', function () {
    $user = User::factory()->create();
    $quizForm = QuizForm::factory()->for($user)->create([
        'questions' => [
            [
                'id' => 1,
                'title' => 'Favorite color?',
                'description' => '',
                'type' => 'Multiple choice',
                'options' => ['Blue', 'Green'],
                'answer' => '',
                'required' => true,
                'media' => [],
                'points' => 10,
            ],
        ],
    ]);
    QuizResponse::factory()->for($quizForm)->create([
        'answers' => [
            1 => 'Blue',
        ],
    ]);
    QuizResponse::factory()->for($quizForm)->create([
        'answers' => [
            1 => 'Green',
        ],
    ]);

    $this->actingAs($user);

    $response = $this->get(route('forms.edit', ['quizForm' => $quizForm->slug]));

    $response
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('FormEditor')
            ->where('quizForm.responses.total', 2)
            ->where('quizForm.responses.questions.0.options.0.count', 1)
        );
});

test('users get a warning when public quiz link already exists', function () {
    $user = User::factory()->create();
    $quizForm = QuizForm::factory()->for($user)->create([
        'slug' => 'my-quiz',
    ]);
    QuizForm::factory()->create([
        'slug' => 'used-quiz',
    ]);

    $this->actingAs($user);

    $this->patch(route('forms.update', $quizForm), [
        'title' => 'Renamed quiz',
        'description' => '',
        'slug' => 'used-quiz',
        'questions' => $quizForm->questions,
        'settings' => $quizForm->settings,
    ])->assertSessionHasErrors([
        'slug' => 'Link sudah digunakan',
    ]);
});

test('authenticated users can update google forms style settings', function () {
    $user = User::factory()->create();
    $quizForm = QuizForm::factory()->for($user)->create([
        'slug' => 'settings-quiz',
    ]);

    $this->actingAs($user);

    $settings = array_merge($quizForm->settings, [
        'isQuiz' => true,
        'emailCollectionMode' => 'responder',
        'collectEmail' => true,
        'sendResponseCopy' => 'always',
        'allowResponseEditing' => true,
        'limitOneResponse' => false,
        'confirmationMessage' => 'Terima kasih, jawaban Anda sudah tercatat.',
        'showSubmitAnotherResponse' => false,
        'showResultsSummary' => true,
        'disableRespondentAutosave' => true,
        'defaultCollectEmailMode' => 'responder',
        'defaultQuestionRequired' => true,
        'defaultQuestionPoints' => 20,
        'maxUploadSize' => 40,
    ]);

    $this->patch(route('forms.update', $quizForm), [
        'title' => 'Settings quiz',
        'description' => 'Quiz with custom settings',
        'slug' => 'settings-quiz',
        'questions' => $quizForm->questions,
        'settings' => $settings,
    ])->assertRedirect(route('forms.edit', ['quizForm' => 'settings-quiz']));

    expect($quizForm->refresh()->settings)
        ->toMatchArray($settings);
});

test('authenticated users can update quiz questions with rows and columns for grid question types', function () {
    $user = User::factory()->create();
    $quizForm = QuizForm::factory()->for($user)->create([
        'slug' => 'grid-quiz',
    ]);

    $this->actingAs($user);

    $questions = [
        [
            'id' => 1,
            'title' => 'Sample Grid Question',
            'description' => '',
            'type' => 'Multiple-choice grid',
            'options' => [],
            'rows' => ['Row 1', 'Row 2'],
            'columns' => ['Column 1', 'Column 2'],
            'answer' => ['0' => 1, '1' => 0],
            'required' => true,
            'points' => 15,
        ],
    ];

    $this->patch(route('forms.update', $quizForm), [
        'title' => 'Grid Quiz Form',
        'description' => 'Quiz with grid question',
        'slug' => 'grid-quiz',
        'questions' => $questions,
        'settings' => $quizForm->settings,
    ])->assertRedirect(route('forms.edit', ['quizForm' => 'grid-quiz']));

    $updatedForm = $quizForm->refresh();
    expect($updatedForm->questions[0])
        ->toMatchArray($questions[0]);
});
