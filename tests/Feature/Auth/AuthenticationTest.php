<?php

use App\Mail\SecurityAlert;
use App\Models\FailedLoginAttempt;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertNoContent();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertNoContent();
});

test('logs warning when user with non-existent email attempts login', function () {
    Log::spy();

    $this->post('/login', [
        'email' => 'nonexistent@example.com',
        'password' => 'password',
    ]);

    Log::shouldHaveReceived('warning')
        ->once()
        ->with('Tentativo di accesso con email inesistente', \Mockery::type('array'));
});

test('logs warning when user with invalid password attempts login', function () {
    Log::spy();

    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    Log::shouldHaveReceived('warning')
        ->once()
        ->with('Tentativo di accesso con password errata', \Mockery::type('array'));
});

test('logs warning when unverified user attempts login', function () {
    Log::spy();

    $user = User::factory()->create(['email_verified_at' => null]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    Log::shouldHaveReceived('warning')
        ->once()
        ->with('Tentativo di accesso con utente non verificato', \Mockery::type('array'));
});

test('logs warning when disabled user attempts login', function () {
    Log::spy();

    $user = User::factory()->create(['is_deleted' => 1]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    Log::shouldHaveReceived('warning')
        ->once()
        ->with('Tentativo di accesso con utente disabilitato', \Mockery::type('array'));
});

test('records failed login attempt when user with invalid password attempts login', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertDatabaseHas('failed_login_attempts', [
        'email' => $user->email,
        'user_id' => $user->id,
        'attempt_type' => 'invalid_credentials',
    ]);
});

test('records failed login attempt when non-existent user attempts login', function () {
    $email = 'nonexistent@example.com';

    $this->post('/login', [
        'email' => $email,
        'password' => 'password',
    ]);

    $this->assertDatabaseHas('failed_login_attempts', [
        'email' => $email,
        'user_id' => null,
        'attempt_type' => 'non_existent_user',
    ]);
});

test('sends security alert email after 5 failed login attempts', function () {
    Mail::fake();

    $user = User::factory()->create();

    // Simulate 5 failed login attempts
    for ($i = 0; $i < 5; $i++) {
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);
    }

    // Verify the mail was sent
    Mail::assertSent(SecurityAlert::class, function (SecurityAlert $mail) use ($user) {
        return $mail->email === $user->email &&
               $mail->totalAttempts >= 5;
    });

    // Verify we have 5 failed attempts in the database
    $this->assertEquals(5, FailedLoginAttempt::where('email', $user->email)->count());
});

test('sends security alert email with correct recipient', function () {
    Mail::fake();
    config(['mail.to_address' => 'admin@test.com']);

    $user = User::factory()->create();

    // Simulate 5 failed login attempts
    for ($i = 0; $i < 5; $i++) {
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);
    }

    // Verify the mail was sent to the correct admin email
    Mail::assertSent(SecurityAlert::class, function (SecurityAlert $mail) {
        return $mail->hasTo('admin@test.com');
    });
});

test('counts only recent failed attempts within 24 hours', function () {
    $user = User::factory()->create();

    // Create an old failed attempt (more than 24 hours ago)
    FailedLoginAttempt::create([
        'email' => $user->email,
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Test Agent',
        'attempt_type' => 'invalid_credentials',
        'created_at' => now()->subDays(2),
    ]);

    // This should count only recent attempts, not the old one
    $recentAttempts = FailedLoginAttempt::countRecentFailedAttempts($user->email);

    expect($recentAttempts)->toBe(0);

    // Add a recent attempt
    FailedLoginAttempt::create([
        'email' => $user->email,
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Test Agent',
        'attempt_type' => 'invalid_credentials',
    ]);

    $recentAttempts = FailedLoginAttempt::countRecentFailedAttempts($user->email);
    expect($recentAttempts)->toBe(1);
});
