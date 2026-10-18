<?php

declare(strict_types=1);

use App\Models\Community;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('communities', ['communities' => Community::orderBy('name')->paginate(30)]));
Route::get('reset-password/{token}', fn (Request $request, string $token) => view('reset-password', ['token' => $token, 'email' => $request->query('email')]))->name('password.reset');
Route::post('reset-password', [App\Http\Controllers\AuthController::class,'reset'])->middleware('throttle:authentication')->name('password.update');
