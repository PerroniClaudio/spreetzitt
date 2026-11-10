<?php

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    // Isolate this read-only report from local application data and external services.
    config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'scout.driver' => 'null']);
    DB::purge('sqlite');
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name')->default('Admin');
        $table->string('surname')->nullable();
        $table->boolean('is_admin')->default(false);
        $table->boolean('is_superadmin')->default(false);
        $table->boolean('is_company_admin')->default(false);
        $table->timestamps();
    });
    foreach (['companies', 'ticket_types', 'ticket_stages'] as $name) {
        Schema::create($name, function (Blueprint $table) use ($name) {
            $table->id();
            $table->string('name');
            if ($name === 'ticket_stages') {
                $table->string('system_key')->nullable();
                $table->softDeletes();
            }
        });
    }
    Schema::create('company_user', function (Blueprint $table) {
        $table->integer('company_id');
        $table->integer('user_id');
    });
    Schema::create('tickets', function (Blueprint $table) {
        $table->id();
        $table->string('description')->default('Ticket di test');
        $table->integer('company_id')->nullable();
        $table->integer('type_id')->nullable();
        $table->integer('stage_id')->nullable();
        $table->integer('admin_user_id')->nullable();
        $table->integer('actual_processing_time')->nullable();
        $table->timestamps();
    });
    foreach (['ticket_messages', 'ticket_status_updates', 'tickets_logs', 'ticket_reminders'] as $name) {
        Schema::create($name, function (Blueprint $table) use ($name) {
            $table->id();
            $table->integer('user_id');
            if ($name !== 'tickets_logs') {
                $table->integer('ticket_id');
            }
            if ($name === 'ticket_status_updates') {
                $table->string('type');
                $table->text('content')->nullable();
                $table->integer('new_stage_id')->nullable();
            }
            $table->timestamps();
        });
    }
    Schema::create('ticket_log_ticket', function (Blueprint $table) {
        $table->integer('ticket_id');
        $table->integer('ticket_log_id');
        $table->timestamps();
    });
    DB::table('ticket_stages')->insert(['id' => 1, 'name' => 'Chiuso', 'system_key' => 'closed']);
    $this->admin = User::create(['is_admin' => true]);
    $this->otherAdmin = User::create(['is_admin' => true]);
    Sanctum::actingAs($this->admin);
    $this->period = ['start_date' => '2026-10-15', 'end_date' => '2026-10-15'];
});

afterEach(function () {
    DB::disconnect('sqlite');
});

function activityTicket(?int $adminId, string $createdAt = '2026-10-01 12:00:00'): int
{
    return DB::table('tickets')->insertGetId(['admin_user_id' => $adminId, 'created_at' => $createdAt]);
}

function activityInteraction(string $table, int $ticketId, int $adminId, string $at, array $extra = []): int
{
    return DB::table($table)->insertGetId(array_merge([
        'ticket_id' => $ticketId, 'user_id' => $adminId, 'created_at' => $at,
    ], $extra));
}

it('includes assigned tickets overlapping the period and excludes earlier closures and future openings', function () {
    $open = activityTicket($this->admin->id);
    $closedDuring = activityTicket($this->admin->id);
    $closedBefore = activityTicket($this->admin->id);
    $closedAtStart = activityTicket($this->admin->id);
    $closedByStage = activityTicket($this->admin->id);
    $openedAtEnd = activityTicket($this->admin->id, '2026-10-15 23:59:59');
    activityTicket($this->admin->id, '2026-10-16 00:00:00');
    activityTicket($this->otherAdmin->id);
    foreach ([$closedDuring => '2026-10-15 14:00:00', $closedBefore => '2026-10-14 23:59:59', $closedAtStart => '2026-10-15 00:00:00'] as $ticket => $at) {
        activityInteraction('ticket_status_updates', $ticket, $this->otherAdmin->id, $at, ['type' => 'closing']);
    }
    activityInteraction('ticket_status_updates', $closedByStage, $this->otherAdmin->id, '2026-10-14 23:59:59', ['type' => 'status', 'new_stage_id' => 1]);

    $response = $this->getJson('/api/ticket-admin-activity?'.http_build_query($this->period))->assertOk();
    expect(array_column($response->json('tickets.data'), 'id'))->toEqualCanonicalizing([$open, $closedDuring, $closedAtStart, $openedAtEnd]);
    foreach ($response->json('tickets.data') as $ticket) {
        expect($ticket['inclusion_reasons'][0]['type'])->toBe('assigned');
    }
});

it('includes all recorded interaction kinds with distinct reasons and no duplicate tickets', function () {
    $ticket = activityTicket($this->otherAdmin->id);
    // An interaction can include a ticket even when it was already closed before the period.
    activityInteraction('ticket_status_updates', $ticket, $this->otherAdmin->id, '2026-10-10 10:00:00', ['type' => 'closing']);
    activityInteraction('ticket_messages', $ticket, $this->admin->id, '2026-10-15 00:00:00');
    activityInteraction('ticket_messages', $ticket, $this->admin->id, '2026-10-15 23:59:59');
    activityInteraction('ticket_status_updates', $ticket, $this->admin->id, '2026-10-15 12:00:00', ['type' => 'note']);
    activityInteraction('ticket_status_updates', $ticket, $this->admin->id, '2026-10-15 13:00:00', ['type' => 'time']);
    activityInteraction('ticket_reminders', $ticket, $this->admin->id, '2026-10-15 14:00:00');
    $log = DB::table('tickets_logs')->insertGetId(['user_id' => $this->admin->id, 'created_at' => '2026-10-15 15:00:00']);
    DB::table('ticket_log_ticket')->insert(['ticket_id' => $ticket, 'ticket_log_id' => $log]);

    $response = $this->getJson('/api/ticket-admin-activity?'.http_build_query($this->period))->assertOk()->assertJsonPath('tickets.total', 1);
    $reasons = collect($response->json('tickets.data.0.inclusion_reasons'))->keyBy('type');
    expect($reasons->keys()->all())->toEqualCanonicalizing(['message', 'update:note', 'update:time', 'ticket_logs_count', 'ticket_reminders_count']);
    expect($reasons['message']['count'])->toBe(2);
});

it('only includes interactions by the reference admin within the selected dates', function () {
    foreach ([['2026-10-14 23:59:59', $this->admin->id], ['2026-10-16 00:00:00', $this->admin->id], ['2026-10-15 12:00:00', $this->otherAdmin->id]] as [$at, $user]) {
        $ticket = activityTicket(null);
        activityInteraction('ticket_messages', $ticket, $user, $at);
        activityInteraction('ticket_status_updates', $ticket, $user, $at, ['type' => 'time']);
        activityInteraction('ticket_reminders', $ticket, $user, $at);
        $log = DB::table('tickets_logs')->insertGetId(['user_id' => $user, 'created_at' => $at]);
        DB::table('ticket_log_ticket')->insert(['ticket_id' => $ticket, 'ticket_log_id' => $log]);
    }
    $this->getJson('/api/ticket-admin-activity?'.http_build_query($this->period))->assertOk()->assertJsonPath('tickets.total', 0);
});

it('includes a ticket for each interaction source independently of assignment', function (string $source, string $reason) {
    $ticket = activityTicket(null);
    if ($source === 'tickets_logs') {
        $log = DB::table('tickets_logs')->insertGetId(['user_id' => $this->admin->id, 'created_at' => '2026-10-15 12:00:00']);
        DB::table('ticket_log_ticket')->insert(['ticket_id' => $ticket, 'ticket_log_id' => $log]);
    } else {
        activityInteraction($source, $ticket, $this->admin->id, '2026-10-15 12:00:00', $source === 'ticket_status_updates' ? ['type' => 'time'] : []);
    }
    $this->getJson('/api/ticket-admin-activity?'.http_build_query($this->period))
        ->assertOk()->assertJsonPath('tickets.total', 1)->assertJsonPath('tickets.data.0.id', $ticket)
        ->assertJsonPath('tickets.data.0.inclusion_reasons.0.type', $reason);
})->with([
    ['ticket_messages', 'message'], ['ticket_status_updates', 'update:time'],
    ['tickets_logs', 'ticket_logs_count'], ['ticket_reminders', 'ticket_reminders_count'],
]);

it('includes both ends of a multi-day interval spanning the daylight-saving change', function () {
    $first = activityTicket(null);
    $last = activityTicket(null);
    activityInteraction('ticket_messages', $first, $this->admin->id, '2026-10-24 00:00:00');
    activityInteraction('ticket_messages', $last, $this->admin->id, '2026-10-26 23:59:59');
    $outside = activityTicket(null);
    activityInteraction('ticket_messages', $outside, $this->admin->id, '2026-10-27 00:00:00');
    $response = $this->getJson('/api/ticket-admin-activity?start_date=2026-10-24&end_date=2026-10-26')->assertOk();
    expect(array_column($response->json('tickets.data'), 'id'))->toEqualCanonicalizing([$first, $last]);
});

it('forces ordinary admins to search their own tickets even if they supply another user ID', function () {
    $mine = activityTicket($this->admin->id);
    activityTicket($this->otherAdmin->id);
    $this->getJson('/api/ticket-admin-activity?'.http_build_query($this->period + ['admin_user_id' => $this->otherAdmin->id]))
        ->assertOk()->assertJsonPath('admin_user_id', $this->admin->id)
        ->assertJsonPath('tickets.total', 1)->assertJsonPath('tickets.data.0.id', $mine);
});

it('allows superadmins to select an admin and defaults to their own account', function () {
    $this->admin->update(['is_superadmin' => true]);
    $other = activityTicket($this->otherAdmin->id);
    $this->getJson('/api/ticket-admin-activity?'.http_build_query($this->period + ['admin_user_id' => $this->otherAdmin->id]))
        ->assertOk()->assertJsonPath('admin_user_id', $this->otherAdmin->id)->assertJsonPath('tickets.data.0.id', $other);
    $this->getJson('/api/ticket-admin-activity?'.http_build_query($this->period))->assertOk()->assertJsonPath('tickets.total', 0);
    $user = User::create(['is_admin' => false]);
    $this->getJson('/api/ticket-admin-activity?'.http_build_query($this->period + ['admin_user_id' => $user->id]))
        ->assertUnprocessable()->assertJsonValidationErrors('admin_user_id');
});

it('validates the date interval and pagination', function () {
    foreach ([[], ['start_date' => '2026-10-16', 'end_date' => '2026-10-15'], ['start_date' => '2026-02-30', 'end_date' => '2026-10-15'], $this->period + ['per_page' => 101], $this->period + ['page' => 0]] as $params) {
        $this->getJson('/api/ticket-admin-activity?'.http_build_query($params))->assertUnprocessable();
    }
});

it('rejects company users even when they have a selected company', function () {
    $user = User::create(['is_admin' => false]);
    DB::table('companies')->insert(['id' => 1, 'name' => 'Azienda']);
    DB::table('company_user')->insert(['company_id' => 1, 'user_id' => $user->id]);
    Sanctum::actingAs($user);
    $this->getJson('/api/ticket-admin-activity?'.http_build_query($this->period))->assertForbidden();
});

it('allows at most seven calendar days between dates, including across daylight-saving changes', function () {
    foreach ([['2026-10-15', '2026-10-22'], ['2026-10-22', '2026-10-29']] as [$start, $end]) {
        $this->getJson("/api/ticket-admin-activity?start_date={$start}&end_date={$end}")->assertOk();
    }
    $this->getJson('/api/ticket-admin-activity?start_date=2026-10-22&end_date=2026-10-30')
        ->assertUnprocessable()->assertJsonValidationErrors('end_date');
});

it('paginates results in a stable order without losing inclusion reasons', function () {
    $ids = [activityTicket($this->admin->id), activityTicket($this->admin->id), activityTicket($this->admin->id)];
    $this->getJson('/api/ticket-admin-activity?'.http_build_query($this->period + ['per_page' => 2, 'page' => 2]))
        ->assertOk()->assertJsonPath('tickets.total', 3)->assertJsonPath('tickets.current_page', 2)
        ->assertJsonCount(1, 'tickets.data')->assertJsonPath('tickets.data.0.id', $ids[0])
        ->assertJsonPath('tickets.data.0.inclusion_reasons.0.type', 'assigned');
});

it('returns the latest closure regardless of author or search dates and the current processing time', function () {
    $ticket = activityTicket($this->admin->id);
    DB::table('tickets')->where('id', $ticket)->update(['actual_processing_time' => 90]);
    activityInteraction('ticket_status_updates', $ticket, $this->otherAdmin->id, '2026-10-16 09:00:00', ['type' => 'closing']);
    activityInteraction('ticket_status_updates', $ticket, $this->otherAdmin->id, '2026-10-17 10:00:00', ['type' => 'closing']);
    $open = activityTicket($this->admin->id);
    DB::table('tickets')->where('id', $open)->update(['actual_processing_time' => 0]);
    $missingTime = activityTicket($this->admin->id);

    $response = $this->getJson('/api/ticket-admin-activity?'.http_build_query($this->period))->assertOk();
    $results = collect($response->json('tickets.data'))->keyBy('id');
    expect($results[$ticket]['actual_processing_time'])->toBe(90)
        ->and($results[$ticket]['closed_at'])->toBe('2026-10-17T08:00:00.000000Z')
        ->and($results[$open]['closed_at'])->toBeNull()
        ->and($results[$open]['actual_processing_time'])->toBe(0)
        ->and($results[$missingTime]['actual_processing_time'])->toBeNull();
});

it('returns all existing time changes in order and the current value without inventing closure values or dates', function () {
    $ticket = activityTicket($this->otherAdmin->id);
    DB::table('tickets')->where('id', $ticket)->update(['actual_processing_time' => 240]);
    $first = activityInteraction('ticket_status_updates', $ticket, $this->otherAdmin->id, '2026-10-12 09:00:00', [
        'type' => 'time', 'content' => 'Tempo di lavorazione effettivo modificato a 01:00',
    ]);
    $second = activityInteraction('ticket_status_updates', $ticket, $this->admin->id, '2026-10-13 10:00:00', [
        'type' => 'time', 'content' => 'Tempo di lavorazione effettivo modificato a 03:00',
    ]);
    activityInteraction('ticket_status_updates', $ticket, $this->admin->id, '2026-10-14 11:00:00', ['type' => 'closing', 'content' => 'Chiusura']);
    $before = DB::table('ticket_status_updates')->count();

    $this->getJson("/api/ticket/{$ticket}/processing-time-history")->assertOk()
        ->assertJsonCount(2, 'entries')
        ->assertJsonPath('entries.0.id', $first)->assertJsonPath('entries.0.actual_processing_time', 60)
        ->assertJsonPath('entries.0.created_at', '2026-10-12T07:00:00.000000Z')
        ->assertJsonPath('entries.0.user.id', $this->otherAdmin->id)
        ->assertJsonPath('entries.1.id', $second)->assertJsonPath('entries.1.actual_processing_time', 180)
        ->assertJsonPath('current.actual_processing_time', 240)
        ->assertJsonPath('current.differs_from_last_recorded', true)
        ->assertJsonMissingPath('current.created_at');
    expect(DB::table('ticket_status_updates')->count())->toBe($before);
});

it('handles zero, long durations and unreadable legacy messages without guessing values', function () {
    $ticket = activityTicket(null);
    foreach (['00:00', '125:30', 'testo non riconosciuto'] as $value) {
        activityInteraction('ticket_status_updates', $ticket, $this->admin->id, '2026-10-15 10:00:00', [
            'type' => 'time', 'content' => 'Tempo di lavorazione effettivo modificato a '.$value,
        ]);
    }
    $this->getJson("/api/ticket/{$ticket}/processing-time-history")->assertOk()
        ->assertJsonPath('entries.0.actual_processing_time', 0)
        ->assertJsonPath('entries.1.actual_processing_time', 7530)
        ->assertJsonPath('entries.2.actual_processing_time', null)
        ->assertJsonPath('entries.2.content', 'Tempo di lavorazione effettivo modificato a testo non riconosciuto')
        ->assertJsonPath('current.actual_processing_time', null)
        ->assertJsonPath('current.differs_from_last_recorded', null);
});

it('returns the current value when no time changes were recorded', function () {
    $ticket = activityTicket(null);
    DB::table('tickets')->where('id', $ticket)->update(['actual_processing_time' => 0]);
    $this->getJson("/api/ticket/{$ticket}/processing-time-history")->assertOk()
        ->assertJsonCount(0, 'entries')->assertJsonPath('current.actual_processing_time', 0)
        ->assertJsonPath('current.differs_from_last_recorded', null);
});

it('denies time history to company users and returns not found for missing tickets', function () {
    $this->getJson('/api/ticket/99999/processing-time-history')->assertNotFound();
    $ticket = activityTicket($this->admin->id);
    $user = User::create(['is_admin' => false]);
    DB::table('companies')->insert(['id' => 1, 'name' => 'Azienda']);
    DB::table('company_user')->insert(['company_id' => 1, 'user_id' => $user->id]);
    Sanctum::actingAs($user);
    $this->getJson("/api/ticket/{$ticket}/processing-time-history")->assertForbidden();
});
