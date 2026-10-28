<?php

use App\Http\Controllers\WorldController as C;
use App\Http\Middleware\DevelopmentOnly;
use App\Services\Classification;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('world', [C::class, 'config']);
    Route::get('categories', fn () => Classification::categories());
    Route::get('locations', [C::class, 'locations']);
    Route::get('zones', [C::class, 'locations']);
    Route::get('statistics', [C::class, 'locations']);
    Route::get('locations/{id}', [C::class, 'detail'])->whereNumber('id');
    Route::get('player', [C::class, 'config']);
    Route::get('inventory', [C::class, 'config']);
    Route::post('actions/{action}', [C::class, 'action'])->whereIn('action', ['travel', 'scavenge', 'rest', 'consume'])->middleware('throttle:60,1,actions');
    Route::prefix('dev')->middleware(DevelopmentOnly::class)->group(function () {
        Route::get('status', [C::class, 'dev']);
        Route::post('import', [C::class, 'import'])->middleware('throttle:2,1,osm-import');
        Route::post('reset', [C::class, 'reset'])->middleware('throttle:5,1,demo-reset');
    });
});
