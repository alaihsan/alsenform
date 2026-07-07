<?php

use App\Models\QuizForm;
use App\Models\UnlockRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('guests are unauthorized to get unlock requests of a form', function () {
    $form = QuizForm::factory()->create();

    $this->getJson(route('forms.unlock-requests.index', $form))
        ->assertStatus(401);
});

test('non-owners are forbidden to get unlock requests of a form', function () {
    $owner = User::factory()->create();
    $nonOwner = User::factory()->create();
    $form = QuizForm::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($nonOwner)
        ->getJson(route('forms.unlock-requests.index', $form))
        ->assertStatus(403);
});

test('owners can fetch unlock requests and approve them', function () {
    $owner = User::factory()->create();
    $form = QuizForm::factory()->create(['user_id' => $owner->id]);

    $req = UnlockRequest::create([
        'quiz_form_id' => $form->id,
        'respondent_identifier' => 'test-resp-123',
        'email' => 'student@test.com',
        'unlock_code' => Hash::make('555666'),
        'status' => 'pending',
    ]);

    $this->actingAs($owner)
        ->getJson(route('forms.unlock-requests.index', $form))
        ->assertStatus(200)
        ->assertJsonFragment(['respondent_identifier' => 'test-resp-123']);

    $this->actingAs($owner)
        ->postJson(route('forms.unlock-requests.approve', $req))
        ->assertStatus(200);

    expect($req->refresh()->status)->toBe('approved');
});

test('respondents can create unlock request, check status and verify code', function () {
    $form = QuizForm::factory()->create([
        'published_at' => now(),
    ]);

    // 1. Create request
    $createResponse = $this->postJson(route('forms.public.unlock-requests.store', $form->slug), [
        'respondent_identifier' => 'test-resp-789',
        'email' => 'anon@gmail.com',
    ])->assertStatus(200)
        ->assertJsonStructure(['code']);

    $req = UnlockRequest::where('quiz_form_id', $form->id)
        ->where('respondent_identifier', 'test-resp-789')
        ->first();

    expect($req)->not->toBeNull();
    expect($req->status)->toBe('pending');
    expect($req->unlock_code)->not->toBe($createResponse->json('code'));

    // 2. Check status
    $this->getJson(route('forms.public.unlock-requests.status', [$form->slug, 'test-resp-789']))
        ->assertStatus(200)
        ->assertJsonPath('status', 'pending');

    // 3. Verify wrong code fails
    $this->postJson(route('forms.public.unlock-verify', $form->slug), [
        'respondent_identifier' => 'test-resp-789',
        'code' => '000000',
    ])->assertStatus(422);

    // 4. Verify correct code succeeds
    $this->postJson(route('forms.public.unlock-verify', $form->slug), [
        'respondent_identifier' => 'test-resp-789',
        'code' => $createResponse->json('code'),
    ])->assertStatus(200)
        ->assertJsonPath('success', true);

    expect($req->refresh()->status)->toBe('approved');
});

test('owners can update lockOnBlur and timeLimit settings', function () {
    $owner = User::factory()->create();
    $form = QuizForm::factory()->create([
        'user_id' => $owner->id,
        'questions' => [
            [
                'id' => 1,
                'title' => 'Sample Question',
                'description' => '',
                'type' => 'Short answer',
                'required' => true,
                'options' => [],
                'media' => [],
            ],
        ],
        'settings' => [
            'collectEmail' => false,
            'showProgress' => false,
            'shuffleQuestions' => false,
        ],
    ]);

    $updatedSettings = array_merge($form->settings, [
        'lockOnBlur' => true,
        'timeLimit' => 15,
    ]);

    $this->actingAs($owner)
        ->patch(route('forms.update', $form), [
            'title' => 'Updated Form Title',
            'slug' => $form->slug,
            'questions' => $form->questions,
            'settings' => $updatedSettings,
        ])
        ->assertRedirect(route('forms.edit', $form));

    $form = $form->refresh();
    expect($form->settings['lockOnBlur'])->toBeTrue();
    expect($form->settings['timeLimit'])->toBe(15);
});
