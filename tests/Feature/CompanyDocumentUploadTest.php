<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');

    $this->admin = User::factory()->create([
        'is_admin' => true,
        'is_company_admin' => false,
        'password' => 'password123',
    ]);

    $this->company = Company::factory()->create();
});

test('admin can upload privacy policy pdf', function () {
    $file = UploadedFile::fake()->create('privacy_policy.pdf', 1000, 'application/pdf');

    $response = $this->actingAs($this->admin)
        ->postJson('/api/companies/privacy-policy', [
            'document' => $file,
            'company_id' => $this->company->id,
        ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'message',
            'file_path',
            'download_url',
        ]);

    $this->company->refresh();
    expect($this->company->privacy_policy_path)->not->toBeNull();
});

test('admin can upload cookie policy pdf', function () {
    $file = UploadedFile::fake()->create('cookie_policy.pdf', 1000, 'application/pdf');

    $response = $this->actingAs($this->admin)
        ->postJson('/api/companies/cookie-policy', [
            'document' => $file,
            'company_id' => $this->company->id,
        ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'message',
            'file_path',
            'download_url',
        ]);

    $this->company->refresh();
    expect($this->company->cookie_policy_path)->not->toBeNull();
});

test('can download privacy policy if exists', function () {
    $this->company->update(['privacy_policy_path' => 'test/privacy_policy.pdf']);

    $response = $this->actingAs($this->admin)
        ->getJson("/api/companies/{$this->company->id}/privacy-policy");

    $response->assertOk()
        ->assertJsonStructure(['download_url']);
});

test('cannot download privacy policy if not exists', function () {
    $response = $this->actingAs($this->admin)
        ->getJson("/api/companies/{$this->company->id}/privacy-policy");

    $response->assertNotFound()
        ->assertJson(['error' => 'Privacy policy non trovata']);
});

test('can download cookie policy if exists', function () {
    $this->company->update(['cookie_policy_path' => 'test/cookie_policy.pdf']);

    $response = $this->actingAs($this->admin)
        ->getJson("/api/companies/{$this->company->id}/cookie-policy");

    $response->assertOk()
        ->assertJsonStructure(['download_url']);
});

test('cannot download cookie policy if not exists', function () {
    $response = $this->actingAs($this->admin)
        ->getJson("/api/companies/{$this->company->id}/cookie-policy");

    $response->assertNotFound()
        ->assertJson(['error' => 'Cookie policy non trovata']);
});

test('validates pdf file type for privacy policy upload', function () {
    $file = UploadedFile::fake()->create('document.txt', 1000, 'text/plain');

    $response = $this->actingAs($this->admin)
        ->postJson('/api/companies/privacy-policy', [
            'document' => $file,
            'company_id' => $this->company->id,
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['document']);
});

test('validates company exists for upload', function () {
    $file = UploadedFile::fake()->create('document.pdf', 1000, 'application/pdf');

    $response = $this->actingAs($this->admin)
        ->postJson('/api/companies/privacy-policy', [
            'document' => $file,
            'company_id' => 999999,
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['company_id']);
});

test('unauthorized user cannot upload documents', function () {
    $user = User::factory()->create([
        'is_admin' => false,
        'is_company_admin' => false,
    ]);

    $file = UploadedFile::fake()->create('document.pdf', 1000, 'application/pdf');

    $response = $this->actingAs($user)
        ->postJson('/api/companies/privacy-policy', [
            'document' => $file,
            'company_id' => $this->company->id,
        ]);

    $response->assertForbidden();
});
