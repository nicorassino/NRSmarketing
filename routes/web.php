<?php

use App\Http\Controllers\CampaignController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductChatController;
use App\Http\Controllers\WhatsAppBridgeController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::middleware(['auth'])->group(function () {
    Route::get('products', [ProductController::class, 'index'])->name('products.index');
    Route::post('products', [ProductController::class, 'store'])->name('products.store');
    Route::patch('products/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::get('products/{product}', [ProductController::class, 'show'])->name('products.show');
    Route::post('products/{product}/generate-positioning', [ProductController::class, 'generatePositioning'])->name('products.generate_positioning');
    Route::post('products/{product}/message-templates/generate', [ProductController::class, 'generateMessageTemplates'])->name('products.message_templates.generate');
    Route::post('products/{product}/documents', [ProductController::class, 'uploadDocument'])->name('products.documents.store');
    Route::post('products/{product}/context-text', [ProductController::class, 'storeTextContext'])->name('products.context_text.store');
    Route::post('products/{product}/analyze', [ProductController::class, 'analyze'])->name('products.analyze');
    Route::get('products/{product}/chat', [ProductChatController::class, 'show'])->name('products.chat.show');
    Route::post('products/{product}/chat/messages', [ProductChatController::class, 'send'])->name('products.chat.send');
    Route::get('settings/whatsapp-bridge', [WhatsAppBridgeController::class, 'show'])->name('whatsapp.bridge.show');
    Route::post('settings/whatsapp-bridge/start', [WhatsAppBridgeController::class, 'start'])->name('whatsapp.bridge.start');

    Route::get('campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
    Route::post('campaigns', [CampaignController::class, 'store'])->name('campaigns.store');
    Route::get('campaigns/{campaign}', [CampaignController::class, 'show'])->name('campaigns.show');
    Route::post('campaigns/{campaign}/runs', [CampaignController::class, 'startRun'])->name('campaigns.runs.start');
    Route::post('runs/{run}/scout', [CampaignController::class, 'runScout'])->name('runs.scout');
    Route::patch('runs/{run}/scout-payload', [CampaignController::class, 'updateScoutPayload'])->name('runs.scout_payload.update');
    Route::post('runs/{run}/executor', [CampaignController::class, 'runExecutor'])->name('runs.executor');
    Route::get('runs/{run}/debug', [CampaignController::class, 'debugRun'])->name('runs.debug');

    Route::get('runs/{run}/inbox', [InboxController::class, 'show'])->name('runs.inbox');
    Route::post('runs/{run}/inbox/generate-drafts', [InboxController::class, 'generateDrafts'])->name('runs.inbox.generate_drafts');
    Route::post('runs/{run}/inbox/review-drafts', [InboxController::class, 'reviewDrafts'])->name('runs.inbox.review_drafts');
    Route::patch('prospects/{prospect}', [InboxController::class, 'updateProspect'])->name('prospects.update');
    Route::patch('messages/{message}', [InboxController::class, 'updateMessage'])->name('messages.update');
    Route::post('messages/{message}/approve', [InboxController::class, 'approveMessage'])->name('messages.approve');
    Route::patch('message-templates/{template}', [ProductController::class, 'updateMessageTemplate'])->name('message_templates.update');
    Route::post('message-templates/{template}/select', [ProductController::class, 'selectMessageTemplate'])->name('message_templates.select');
});

require __DIR__.'/auth.php';
