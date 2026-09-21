<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\DocumentController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/documents');

Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');

Route::get('/documents/{document}/chat', [ChatController::class, 'show'])->name('chat.show');
Route::post('/documents/{document}/chat', [ChatController::class, 'store'])->name('chat.store');
