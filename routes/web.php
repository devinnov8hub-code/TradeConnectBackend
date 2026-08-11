<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'message' => 'Trade Connect API',
        'version' => 'v1',
        'base_url' => url('/api/v1'),
    ]);
});
