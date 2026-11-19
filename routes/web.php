<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FaqController;
use App\Http\Controllers\Admin\KnowledgeDocumentController;
use App\Http\Controllers\Admin\SibBmController;
use App\Http\Controllers\ChatPageController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('admin.dashboard')
        : redirect()->route('admin.login');
})->name('home');

Route::get('/chat', [ChatPageController::class, 'index'])->name('chat.index');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
        Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
    });

    Route::middleware(['auth', 'admin.active'])->group(function () {
        Route::get('/', fn () => redirect()->route('admin.dashboard'));
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::resource('categories', CategoryController::class)->except(['show']);

        Route::resource('faqs', FaqController::class)->except(['show']);
        Route::patch('/faqs/{faq}/toggle-status', [FaqController::class, 'toggleStatus'])
            ->name('faqs.toggle-status');

        Route::post('/knowledge/process-pending', [KnowledgeDocumentController::class, 'processPending'])
            ->name('knowledge.process-pending');
        Route::resource('knowledge', KnowledgeDocumentController::class);
        Route::get('/knowledge/{knowledge}/download', [KnowledgeDocumentController::class, 'download'])
            ->name('knowledge.download');
        Route::post('/knowledge/{knowledge}/index', [KnowledgeDocumentController::class, 'indexDocument'])
            ->name('knowledge.index-document');

        Route::get('/sibbm', [SibBmController::class, 'index'])->name('sibbm.index');
        Route::post('/sibbm/sync', [SibBmController::class, 'sync'])->name('sibbm.sync');
        Route::post('/sibbm/import', [SibBmController::class, 'importJson'])->name('sibbm.import');

        Route::get('/modules/{module}', [DashboardController::class, 'modulePlaceholder'])->name('modules.show');
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    });
});
