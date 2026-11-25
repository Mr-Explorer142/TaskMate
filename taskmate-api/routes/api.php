<?php

use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\PointController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfileLinkController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/email/verify', [AuthController::class, 'verifyEmail'])
    ->middleware('throttle:6,1');

Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])
    ->name('social.redirect');
Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])
    ->name('social.callback');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/email/resend', [AuthController::class, 'resendVerification'])
        ->middleware('throttle:3,1');

    Route::put('/profile', [ProfileController::class, 'update']);

    Route::post('/profile/cv', [ProfileController::class, 'uploadCv']);
    Route::delete('/profile/cv', [ProfileController::class, 'deleteCv']);

    Route::get('/profile/links', [ProfileLinkController::class, 'index']);
    Route::post('/profile/links', [ProfileLinkController::class, 'store']);
    Route::patch('/profile/links/reorder', [ProfileLinkController::class, 'reorder']);
    Route::put('/profile/links/{link}', [ProfileLinkController::class, 'update']);
    Route::delete('/profile/links/{link}', [ProfileLinkController::class, 'destroy']);

    Route::get('/users/{user}/cv', [ProfileController::class, 'downloadCv']);
    Route::get('/users/{user}/reviews', [ReviewController::class, 'forUser']);
    Route::get('/users/{user}', [ProfileController::class, 'show']);

    Route::get('/tasks', [TaskController::class, 'index']);
    Route::get('/my/tasks', [TaskController::class, 'myTasks']);
    Route::post('/tasks', [TaskController::class, 'store']);
    Route::get('/tasks/{task}', [TaskController::class, 'show']);
    Route::get('/tasks/{task}/team', [TaskController::class, 'team']);
    Route::patch('/tasks/{task}/start', [TaskController::class, 'start']);
    Route::patch('/tasks/{task}/complete', [TaskController::class, 'complete']);
    Route::get('/tasks/{task}/reviews/pending', [ReviewController::class, 'pending']);
    Route::post('/tasks/{task}/reviews', [ReviewController::class, 'store']);
    Route::put('/tasks/{task}', [TaskController::class, 'update']);
    Route::patch('/tasks/{task}/cancel', [TaskController::class, 'cancel']);
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy']);

    Route::post('/tasks/{task}/apply', [ApplicationController::class, 'apply'])
        ->middleware('throttle:30,1');
    Route::get('/tasks/{task}/applications', [ApplicationController::class, 'taskApplications']);

    Route::get('/my/applications', [ApplicationController::class, 'myApplications']);
    Route::delete('/applications/{application}', [ApplicationController::class, 'withdraw']);
    Route::patch('/applications/{application}/accept', [ApplicationController::class, 'accept']);
    Route::patch('/applications/{application}/reject', [ApplicationController::class, 'reject']);
    Route::patch('/applications/{application}/leave', [ApplicationController::class, 'leave']);

    Route::get('/points', [PointController::class, 'index']);
    Route::get('/points/transactions', [PointController::class, 'transactions']);

    Route::post('/conversations', [ConversationController::class, 'store']);
    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::get('/conversations/unread-count', [ConversationController::class, 'unreadCount']);
    Route::get('/conversations/{conversation}/messages', [ConversationController::class, 'messages']);
    Route::post('/conversations/{conversation}/messages', [ConversationController::class, 'sendMessage'])
        ->middleware('throttle:60,1');
    Route::post('/conversations/{conversation}/read', [ConversationController::class, 'markRead']);
});
