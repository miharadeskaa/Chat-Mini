<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\AskController;
use App\Http\Controllers\AskStreamController; 

// Page d'accueil : Redirection 
Route::get('/', function () {
    return Auth::check() ? redirect()->route('chat.index') : redirect()->route('login');
})->name('home');

// Toutes les routes nécessitant d'être connecté
Route::middleware(['auth', 'verified'])->group(function () {
    
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    //  CHAT CLASSIQUE
    Route::prefix('chat')->name('chat.')->controller(AskController::class)->group(function () {
        
        // Configuration
        Route::patch('/model', 'updateModel')->name('update_model'); 
        Route::patch('/instructions', 'updateInstructions')->name('update_instructions'); 
        Route::patch('/theme', 'toggleTheme')->name('theme');
        
        // Nouvelle conversation
        Route::get('/', 'index')->name('index');
        Route::post('/', 'ask')->name('store.new');
        
        // Conversation existante ({conversation})
        Route::get('/{conversation}', 'show')->name('show');
        Route::post('/{conversation}', 'ask')->name('store.existing');
        Route::delete('/{conversation}', 'destroy')->name('destroy');
        
    });

    //  CHAT STREAMING
    Route::prefix('ask-stream')->name('stream.')->controller(AskStreamController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'stream')->name('post');
    });

});

require __DIR__.'/settings.php';