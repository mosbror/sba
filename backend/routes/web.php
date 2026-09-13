<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function (): array {
    return [
        'name' => 'SBA API',
        'status' => 'ok',
        'api' => '/api/health',
    ];
});
