<?php

use App\Models\QuizForm;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

test('teacher can invite another teacher as collaborator', function () {
    $ownerTeacher = User::factory()->create([
        'name' => 'Guru Pemilik',
        'email' => 'owner@sekolah.sch.id',
        'role' => 'guru',
        'is_admin' => false,
    ]);

    $collabTeacher = User::factory()->create([
        'name' => 'Guru Rekan',
        'email' => 'rekan@sekolah.sch.id',
        'role' => 'guru',
        'is_admin' => false,
    ]);

    $quizForm = QuizForm::factory()->create([
        'user_id' => $ownerTeacher->id,
        'title' => 'Kuis Matematika Bersama',
    ]);

    $this->actingAs($ownerTeacher);

    $response = $this->post(route('forms.collaborators.store', $quizForm), [
        'user_id' => $collabTeacher->id,
    ]);

    $response->assertSessionHasNoErrors();
    expect($quizForm->collaborators()->where('user_id', $collabTeacher->id)->exists())->toBeTrue();
});

test('teacher cannot invite student as collaborator', function () {
    $ownerTeacher = User::factory()->create([
        'role' => 'guru',
        'is_admin' => false,
    ]);

    $student = User::create([
        'name' => 'Murid Cerdas',
        'nis' => '99887766',
        'password' => Hash::make('secret'),
        'role' => 'siswa',
    ]);

    $quizForm = QuizForm::factory()->create([
        'user_id' => $ownerTeacher->id,
        'title' => 'Kuis Fisika',
    ]);

    $this->actingAs($ownerTeacher);

    $response = $this->post(route('forms.collaborators.store', $quizForm), [
        'user_id' => $student->id,
    ]);

    $response->assertSessionHasErrors(['user_id']);
    expect($quizForm->collaborators()->where('user_id', $student->id)->exists())->toBeFalse();
});

test('teacher cannot invite owner or duplicate collaborator', function () {
    $ownerTeacher = User::factory()->create(['role' => 'guru']);
    $collabTeacher = User::factory()->create(['role' => 'guru']);

    $quizForm = QuizForm::factory()->create([
        'user_id' => $ownerTeacher->id,
    ]);

    $quizForm->collaborators()->attach($collabTeacher->id, ['role' => 'editor']);

    $this->actingAs($ownerTeacher);

    // Cannot invite owner
    $response1 = $this->post(route('forms.collaborators.store', $quizForm), [
        'user_id' => $ownerTeacher->id,
    ]);
    $response1->assertSessionHasErrors(['user_id']);

    // Cannot invite existing collaborator
    $response2 = $this->post(route('forms.collaborators.store', $quizForm), [
        'user_id' => $collabTeacher->id,
    ]);
    $response2->assertSessionHasErrors(['user_id']);
});

test('non-owner non-admin cannot invite collaborators', function () {
    $ownerTeacher = User::factory()->create(['role' => 'guru']);
    $otherTeacher = User::factory()->create(['role' => 'guru']);
    $targetTeacher = User::factory()->create(['role' => 'guru']);

    $quizForm = QuizForm::factory()->create([
        'user_id' => $ownerTeacher->id,
    ]);

    $this->actingAs($otherTeacher);

    $response = $this->post(route('forms.collaborators.store', $quizForm), [
        'user_id' => $targetTeacher->id,
    ]);

    $response->assertForbidden();
});

test('collaborator can access form editor and update quiz form', function () {
    $ownerTeacher = User::factory()->create(['role' => 'guru', 'name' => 'Guru Asli']);
    $collabTeacher = User::factory()->create(['role' => 'guru', 'name' => 'Guru Kolaborator']);

    $quizForm = QuizForm::factory()->create([
        'user_id' => $ownerTeacher->id,
        'title' => 'Kuis Biologi',
        'questions' => [
            [
                'id' => 1,
                'title' => 'Pertanyaan 1',
                'type' => 'Multiple choice',
                'options' => ['A', 'B'],
                'answer' => 0,
            ],
        ],
    ]);

    $quizForm->collaborators()->attach($collabTeacher->id, ['role' => 'editor']);

    $this->actingAs($collabTeacher);

    // Collaborator can open editor
    $response = $this->get(route('forms.edit', $quizForm));
    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('FormEditor')
        ->where('quizForm.title', 'Kuis Biologi')
        ->where('quizForm.isOwner', false)
        ->has('quizForm.collaborators', 1)
    );

    // Collaborator can update form
    $updateResponse = $this->patch(route('forms.update', $quizForm), [
        'title' => 'Kuis Biologi Diedit Kolaborator',
        'slug' => $quizForm->slug,
        'description' => 'Deskripsi baru',
        'questions' => $quizForm->questions,
        'settings' => $quizForm->settings,
        'published' => true,
    ]);

    $updateResponse->assertSessionHasNoErrors();
    expect($quizForm->fresh()->title)->toBe('Kuis Biologi Diedit Kolaborator');
});

test('collaborating forms appear on collaborator dashboard with collaborator flag', function () {
    $ownerTeacher = User::factory()->create(['role' => 'guru', 'name' => 'Guru Senior']);
    $collabTeacher = User::factory()->create(['role' => 'guru', 'name' => 'Guru Mitra']);

    $quizForm = QuizForm::factory()->create([
        'user_id' => $ownerTeacher->id,
        'title' => 'Kuis Sejarah Nasional',
    ]);

    $quizForm->collaborators()->attach($collabTeacher->id, ['role' => 'editor']);

    $this->actingAs($collabTeacher);

    $response = $this->get(route('dashboard'));
    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')
        ->has('recentForms', 1)
        ->where('recentForms.0.title', 'Kuis Sejarah Nasional')
        ->where('recentForms.0.isCollaborator', true)
        ->where('recentForms.0.ownerName', 'Guru Senior')
    );
});

test('collaborator cannot delete quiz form', function () {
    $ownerTeacher = User::factory()->create(['role' => 'guru']);
    $collabTeacher = User::factory()->create(['role' => 'guru']);

    $quizForm = QuizForm::factory()->create([
        'user_id' => $ownerTeacher->id,
    ]);

    $quizForm->collaborators()->attach($collabTeacher->id, ['role' => 'editor']);

    $this->actingAs($collabTeacher);

    $response = $this->delete(route('forms.destroy', $quizForm));
    $response->assertForbidden();

    $responseForce = $this->delete(route('forms.force-delete', $quizForm));
    $responseForce->assertForbidden();

    expect(QuizForm::where('id', $quizForm->id)->exists())->toBeTrue();
});

test('owner can remove collaborator and collaborator can leave', function () {
    $ownerTeacher = User::factory()->create(['role' => 'guru']);
    $collabTeacher1 = User::factory()->create(['role' => 'guru']);
    $collabTeacher2 = User::factory()->create(['role' => 'guru']);

    $quizForm = QuizForm::factory()->create([
        'user_id' => $ownerTeacher->id,
    ]);

    $quizForm->collaborators()->attach([$collabTeacher1->id, $collabTeacher2->id]);

    // 1. Owner removes collabTeacher1
    $this->actingAs($ownerTeacher);
    $response1 = $this->delete(route('forms.collaborators.destroy', [
        'quizForm' => $quizForm->id,
        'user' => $collabTeacher1->id,
    ]));
    $response1->assertSessionHasNoErrors();
    expect($quizForm->collaborators()->where('user_id', $collabTeacher1->id)->exists())->toBeFalse();

    // 2. collabTeacher2 removes themselves (leaves)
    $this->actingAs($collabTeacher2);
    $response2 = $this->delete(route('forms.collaborators.destroy', [
        'quizForm' => $quizForm->id,
        'user' => $collabTeacher2->id,
    ]));
    $response2->assertSessionHasNoErrors();
    expect($quizForm->collaborators()->where('user_id', $collabTeacher2->id)->exists())->toBeFalse();
});
