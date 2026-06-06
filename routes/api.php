<?php

use App\Http\Controllers\AcademicoController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\IaController;
use App\Http\Controllers\PagosController;
use Illuminate\Support\Facades\Route;

// Auth (public — solo login, el registro lo hace el admin)
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
});

// Visualizar documentos (auth por query param ?token=)
Route::get('documents/view', [DocumentController::class, 'view']);

// Protected routes
Route::middleware('jwt')->group(function () {

    Route::prefix('auth')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
    });

    // ms-ia
    Route::prefix('ia')->group(function () {
        Route::post('families/{familyId}/risk-score', [IaController::class, 'riskScore']);
        Route::get('families/{familyId}/risk-score/history', [IaController::class, 'riskScoreHistory']);
        Route::post('families/{familyId}/cluster', [IaController::class, 'cluster']);
        Route::get('families/{familyId}/cluster', [IaController::class, 'getCluster']);
        Route::post('payment-events', [IaController::class, 'paymentEvent']);
        Route::post('ocr', [IaController::class, 'ocr']);
    });

    // ms-pagos
    Route::prefix('pagos')->group(function () {
        Route::get('payments', [PagosController::class, 'getAllPayments']);
        Route::post('payments', [PagosController::class, 'createPayment']);
        Route::get('families/{familyId}/balance', [PagosController::class, 'getBalance']);
        Route::post('webhooks', [PagosController::class, 'processWebhook']);
    });

    // ms-academico
    Route::prefix('academico')->group(function () {
        Route::post('students/enroll', [AcademicoController::class, 'enrollStudent']);
        Route::get('students/{studentId}', [AcademicoController::class, 'getStudent']);
        Route::patch('students/{studentId}/attendance', [AcademicoController::class, 'updateAttendance']);
    });

    // documentos
    Route::post('documents/upload', [DocumentController::class, 'upload']);

    // admin — solo ADMIN
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('users',                [AdminController::class, 'listUsers']);
        Route::post('users',               [AdminController::class, 'createUser']);
        Route::patch('users/{id}/family',  [AdminController::class, 'assignFamily']);
    });
});
