<?php

use App\Http\Controllers\AcademicoController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\IaController;
use App\Http\Controllers\PagosController;
use Illuminate\Support\Facades\Route;

// Auth (public)
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login',    [AuthController::class, 'login']);
});

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
        Route::post('families/{familyId}/cluster', [IaController::class, 'cluster']);
        Route::post('payment-events', [IaController::class, 'paymentEvent']);
        Route::post('ocr', [IaController::class, 'ocr']);
    });

    // ms-pagos
    Route::prefix('pagos')->group(function () {
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
});
