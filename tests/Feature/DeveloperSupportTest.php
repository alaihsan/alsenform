<?php

use App\Models\Donation;
use App\Models\User;
use App\Models\Withdrawal;

test('guests are redirected when accessing support endpoints', function () {
    $this->postJson(route('support.suggestion'), [
        'subject' => 'Idea',
        'message' => 'Detail message',
    ])->assertStatus(401);

    $this->postJson(route('support.donate'), [
        'donor_name' => 'John',
        'amount' => 10000,
    ])->assertStatus(401);

    $this->getJson(route('support.stats'))
        ->assertStatus(401);

    $this->postJson(route('support.withdraw'), [
        'amount' => 5000,
        'bank_name' => 'BCA',
        'account_number' => '123456',
        'account_name' => 'John Doe',
    ])->assertStatus(401);
});

test('authenticated non-admin users cannot access admin support endpoints', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $this->actingAs($user);

    $this->getJson(route('support.stats'))
        ->assertStatus(403);

    $this->postJson(route('support.withdraw'), [
        'amount' => 5000,
        'bank_name' => 'BCA',
        'account_number' => '123456',
        'account_name' => 'John Doe',
    ])->assertStatus(403);
});

test('authenticated users can submit a suggestion', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->postJson(route('support.suggestion'), [
        'subject' => 'Nice App',
        'message' => 'I really love this application. Good job!',
    ]);

    $response->assertSuccessful();
    $response->assertJsonPath('success', true);

    $this->assertDatabaseHas('suggestions', [
        'user_id' => $user->id,
        'subject' => 'Nice App',
        'message' => 'I really love this application. Good job!',
    ]);
});

test('authenticated users can initiate a donation', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->postJson(route('support.donate'), [
        'donor_name' => 'Super Donor',
        'amount' => 25000,
        'message' => 'Keep up the good work!',
    ]);

    $response->assertSuccessful();
    $response->assertJsonPath('success', true);
    $response->assertJsonStructure([
        'success',
        'donation' => ['id', 'donor_name', 'amount', 'message', 'status', 'payment_reference'],
    ]);

    $this->assertDatabaseHas('donations', [
        'user_id' => $user->id,
        'donor_name' => 'Super Donor',
        'amount' => 25000,
        'status' => 'pending',
    ]);
});

test('authenticated users can confirm a donation (payment simulation)', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $donation = Donation::query()->create([
        'user_id' => $user->id,
        'donor_name' => 'Supporter',
        'amount' => 50000,
        'payment_reference' => 'DON-TEST1234',
        'status' => 'pending',
    ]);

    $response = $this->postJson(route('support.confirm-donation', $donation));

    $response->assertSuccessful();
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('donation.status', 'success');

    $this->assertDatabaseHas('donations', [
        'id' => $donation->id,
        'status' => 'success',
    ]);
});

test('authenticated users cannot confirm another users donation', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $this->actingAs($otherUser);

    $donation = Donation::query()->create([
        'user_id' => $owner->id,
        'donor_name' => 'Supporter',
        'amount' => 50000,
        'payment_reference' => 'DON-OWNER',
        'status' => 'pending',
    ]);

    $this->postJson(route('support.confirm-donation', $donation))
        ->assertForbidden();
});

test('authenticated admin users can view support stats and suggestions', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    // Create a successful donation
    Donation::query()->create([
        'user_id' => $admin->id,
        'donor_name' => 'Donor A',
        'amount' => 15000,
        'payment_reference' => 'DON-A',
        'status' => 'success',
    ]);

    // Create a withdrawal
    Withdrawal::query()->create([
        'user_id' => $admin->id,
        'amount' => 5000,
        'bank_name' => 'BCA',
        'account_number' => '111',
        'account_name' => 'Dev',
        'status' => 'approved',
    ]);

    $response = $this->getJson(route('support.stats'));

    $response->assertSuccessful();
    $response->assertJson([
        'total_received' => 15000,
        'total_withdrawn' => 5000,
        'balance' => 10000,
    ]);
    $response->assertJsonStructure(['supporters', 'withdrawals', 'suggestions']);
});

test('authenticated admin users cannot withdraw more than their balance', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    // Create successful donation of 10000
    Donation::query()->create([
        'user_id' => $admin->id,
        'donor_name' => 'Donor A',
        'amount' => 10000,
        'payment_reference' => 'DON-A',
        'status' => 'success',
    ]);

    // Attempt to withdraw 15000 (which is more than 10000)
    $response = $this->postJson(route('support.withdraw'), [
        'amount' => 15000,
        'bank_name' => 'BCA',
        'account_number' => '222',
        'account_name' => 'Dev',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['amount']);
});

test('authenticated admin users can withdraw within their balance', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    // Create successful donation of 10000
    Donation::query()->create([
        'user_id' => $admin->id,
        'donor_name' => 'Donor A',
        'amount' => 10000,
        'payment_reference' => 'DON-A',
        'status' => 'success',
    ]);

    // Withdraw 6000
    $response = $this->postJson(route('support.withdraw'), [
        'amount' => 6000,
        'bank_name' => 'BCA',
        'account_number' => '222',
        'account_name' => 'Dev',
    ]);

    $response->assertSuccessful();
    $response->assertJsonPath('success', true);

    $this->assertDatabaseHas('withdrawals', [
        'user_id' => $admin->id,
        'amount' => 6000,
        'bank_name' => 'BCA',
        'status' => 'approved',
    ]);
});
