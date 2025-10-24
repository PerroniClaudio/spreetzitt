<?php

use Illuminate\Support\Facades\Log;

it('has a news log channel configured', function () {
    $channels = config('logging.channels');

    expect(array_key_exists('news', $channels))->toBeTrue();

    $newsChannel = $channels['news'];

    // driver should be daily and path should point to storage/logs/news.log
    expect($newsChannel['driver'])->toBe('daily');
    expect($newsChannel['path'])->toBe(storage_path('logs/news.log'));
});
