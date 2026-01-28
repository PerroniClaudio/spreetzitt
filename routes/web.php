<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return [
        'app_name' => config('app.name'),
        'Laravel' => app()->version(),
        'timezone' => config('app.timezone'),
        'current_time' => now()->toDateTimeString(),
        'environment' => config('app.env'),
    ];
});

Route::get('/info', function () {
    phpinfo();
});

Route::get('/test-scraper', function () {

    $source = \App\Models\NewsSource::where('slug', 'integys')->first();
    dispatch(new \App\Jobs\FetchNewsForSource($source));
});

require __DIR__.'/auth.php';
require __DIR__.'/webhook.php';

Route::middleware(['throttle:5,1', 'auth:sanctum'])->group(function () {
    Route::post('/two-factor-authentication-challenge', [App\Http\Controllers\UserController::class, 'twoFactorChallenge'])->name('two-factor-challenge-user');
});

Route::get('/test', function () {
    return response()->json(['message' => 'Test route is working']);
});

Route::get('/send-assign-email', function () {
    $assignUpdate = \App\Models\TicketStatusUpdate::where('type', 'assign')->first();
    dispatch(new \App\Jobs\SendUpdateEmail($assignUpdate, true));
    return response()->json(['message' => 'Test route is working. ' . $assignUpdate->id]);
});

Route::get('/debug/tickets-missing-user', function () {
    return \App\Models\Ticket::with('user')
        ->where(function ($query) {
            $query->whereNull('user_id')
                ->orWhereDoesntHave('user')
                ->orWhereHas('user', function ($userQuery) {
                    $userQuery->whereNull('name');
                });
        })
        ->get(['id', 'user_id']);
});
