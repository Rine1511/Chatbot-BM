<?php

use App\Http\Controllers\Api\V1\ChatController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/chat')
    ->middleware('widget.origin')
    ->name('api.v1.chat.')
    ->group(function (): void {
        Route::post('/conversations', [ChatController::class, 'startConversation'])
            ->middleware('throttle:chat-start')
            ->name('conversations.start');

        Route::get('/conversations/{sessionToken}', [ChatController::class, 'showConversation'])
            ->middleware('throttle:chat-read')
            ->name('conversations.show');

        Route::post('/messages', [ChatController::class, 'sendMessage'])
            ->middleware('throttle:chat-message')
            ->name('messages.store');
    });
