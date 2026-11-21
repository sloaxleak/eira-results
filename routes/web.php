<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Portal\PortalController;
use App\Http\Controllers\Staff\AuditController;
use App\Http\Controllers\Staff\DashboardController;
use App\Http\Controllers\Staff\DocumentController;
use App\Http\Controllers\Staff\UserController;
use App\Http\Middleware\EnsurePatientStudySession;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/consulta');

Route::prefix('consulta')->name('portal.')->controller(PortalController::class)->group(function () {
    Route::get('/', 'show')->name('show');
    Route::post('/', 'redeem')->name('redeem');
    Route::post('/codigo', 'resend')->name('resend');
    Route::post('/salir', 'logout')->name('logout');

    Route::middleware(EnsurePatientStudySession::class)->group(function () {
        Route::get('/estudio', 'study')->name('study');
        Route::get('/documentos/{document}/{mode}', 'document')
            ->whereUuid('document')
            ->whereIn('mode', ['ver', 'descargar'])
            ->name('document');
    });
});

Route::middleware('guest')->group(function () {
    Route::get('/acceso', [LoginController::class, 'show'])->name('login');
    Route::post('/acceso', [LoginController::class, 'login'])->name('login.attempt');
});

Route::post('/salir', [LoginController::class, 'logout'])->middleware('auth')->name('logout');
Route::post('/acceso/demo/{role}', [LoginController::class, 'quick'])
    ->whereIn('role', ['operator', 'publisher', 'admin'])
    ->middleware('throttle:30,1')
    ->name('login.demo');

Route::middleware(['auth', 'can:staff.access'])->prefix('staff')->name('staff.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/bandeja-demo', [DashboardController::class, 'outbox'])->name('outbox');
    Route::get('/correo-ejemplo', [DashboardController::class, 'emailPreview'])->name('email-preview');
    Route::get('/bitacora', [AuditController::class, 'index'])->name('audit');

    Route::controller(UserController::class)->prefix('usuarios')->name('users.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/nuevo', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{user}/editar', 'edit')->name('edit');
        Route::put('/{user}', 'update')->name('update');
        Route::post('/{user}/estado', 'toggle')->name('toggle');
    });

    Route::controller(DocumentController::class)->group(function () {
        Route::get('/recepcion', 'index')->name('intake');
        Route::get('/publicacion', 'publication')->name('publication');
        Route::post('/documentos', 'store')->name('documents.store');
        Route::get('/documentos/{document}', 'show')->name('documents.show');
        Route::get('/documentos/{document}/archivo/{variant?}', 'file')
            ->whereIn('variant', ['original', 'entrega'])
            ->name('documents.file');
        Route::post('/documentos/{document}/asociar', 'associate')->name('documents.associate');
        Route::post('/documentos/{document}/revisar', 'review')->name('documents.review');
        Route::post('/documentos/{document}/rechazar', 'reject')->name('documents.reject');
        Route::post('/documentos/{document}/publicar', 'publish')->name('documents.publish');
        Route::post('/documentos/{document}/revocar', 'revoke')->name('documents.revoke');
        Route::post('/avisos/{notification}/reintentar', 'retryNotification')->name('notifications.retry');
        Route::post('/contactos/{contact}/confirmar', 'verifyContact')->name('contacts.verify');
    });
});
