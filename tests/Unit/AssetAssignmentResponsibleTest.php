<?php

use App\Models\Hardware;
use App\Models\Software;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

uses(TestCase::class);

it('exposes the responsible user through hardware and software assignment pivots', function () {
    expect((new Hardware)->users()->getPivotColumns())
        ->toContain('responsible_user_id')
        ->and((new Software)->users()->getPivotColumns())
        ->toContain('responsible_user_id');
});

it('registers dedicated endpoints for updating asset assignments', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => in_array('PATCH', $route->methods(), true))
        ->map(fn ($route) => $route->uri());

    expect($routes)
        ->toContain('api/hardware-users/{hardware}')
        ->and($routes)
        ->toContain('api/software-users/{software}');
});
