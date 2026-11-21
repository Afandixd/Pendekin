<?php

use App\Http\Controllers\LinkController;
use Illuminate\Support\Facades\Route;

// Membuat short link
Route::post('/links', [LinkController::class, 'store']);
