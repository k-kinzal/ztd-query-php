<?php

declare(strict_types=1);

use App\Http\Controllers\{AccountController, AttachmentController, AuthController, CommunityController, DiscussionController, ModerationController};
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:authentication')->group(function (): void {
    Route::post('register', [AuthController::class,'register']);
    Route::post('login', [AuthController::class,'login']);
    Route::post('forgot-password', [AuthController::class,'forgot']);
    Route::post('reset-password', [AuthController::class,'reset']);
});
Route::get('email/verify/{id}/{hash}', [AuthController::class,'verify'])->middleware(['signed','throttle:6,1'])->name('verification.verify');
Route::get('communities', [CommunityController::class,'index']);
Route::get('communities/{community}/boards', [CommunityController::class,'boards']);
Route::get('boards/{board}/topics', [DiscussionController::class,'index']);
Route::get('topics/{topic}', [DiscussionController::class,'show']);
Route::get('topics/{topic}/posts', [DiscussionController::class,'posts']);
Route::get('attachments/{attachment}', [AttachmentController::class,'show'])->name('attachments.show');
Route::middleware(['auth:sanctum','active'])->group(function (): void {
    Route::post('logout', [AuthController::class,'logout']);
    Route::post('email/resend', [AuthController::class,'resend'])->middleware('throttle:6,1');
    Route::get('account/export', [AccountController::class,'export'])->middleware('throttle:3,60');
    Route::delete('account', [AccountController::class,'erase']);
    Route::get('notifications', [AccountController::class,'notifications']);
    Route::put('notifications/{notification}', [AccountController::class,'read']);
    Route::middleware(['verified','throttle:publishing'])->group(function (): void {
        Route::post('communities', [CommunityController::class,'store']);
        Route::post('communities/{community}/join', [CommunityController::class,'join']);
        Route::post('communities/{community}/boards', [CommunityController::class,'createBoard']);
        Route::get('communities/{community}/members', [CommunityController::class,'members']);
        Route::put('communities/{community}/members/{user}', [CommunityController::class,'role']);
        Route::post('communities/{community}/transfer', [CommunityController::class,'transfer']);
        Route::get('communities/{community}/audit', [CommunityController::class,'audit']);
        Route::put('boards/{board}/archive', [CommunityController::class, 'archiveBoard']);
        Route::post('boards/{board}/topics', [DiscussionController::class,'store']);
        Route::post('topics/{topic}/replies', [DiscussionController::class,'reply']);
        Route::post('topics/{topic}/transition', [DiscussionController::class,'transition']);
        Route::put('posts/{post}', [DiscussionController::class,'edit']);
        Route::get('posts/{post}/revisions', [DiscussionController::class,'revisions']);
        Route::put('topics/{topic}/subscription', [DiscussionController::class,'subscribe']);
        Route::delete('topics/{topic}/subscription', [DiscussionController::class,'unsubscribe']);
        Route::put('posts/{post}/reaction', [DiscussionController::class,'react']);
        Route::post('posts/{post}/attachments', [AttachmentController::class,'store']);
        Route::post('posts/{post}/reports', [ModerationController::class,'report']);
        Route::get('communities/{community}/reports', [ModerationController::class,'queue']);
        Route::put('reports/{report}', [ModerationController::class,'resolve']);
        Route::post('communities/{community}/restrictions', [ModerationController::class,'restrict']);
        Route::delete('restrictions/{ban}', [ModerationController::class,'revoke']);
    });
});
