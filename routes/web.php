<?php
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RedirectController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/health', function () {
    try {
        DB::connection()->getPdo();
        return response()->json(['status' => 'ok', 'database' => 'up'], 200);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'database' => 'down'], 503);
    }
});

// Redirect short link
Route::get('/{code}', [RedirectController::class, 'redirect'])
    ->where('code', '[A-Za-z0-9-]+')
    ->name('links.redirect');