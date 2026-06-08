<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AskController;
use App\Http\Controllers\ChatController;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    // 1. Sauvegarder le changement de modèle (À METTRE EN PREMIER)
    Route::patch('/chat/model', [AskController::class, 'updateModel'])->name('chat.update_model');

    // 2. Afficher l'interface de chat (Nouvelle conversation)
    Route::get('/chat', [AskController::class, 'index'])->name('chat.index');
    
    // 3. Afficher une conversation existante
    Route::get('/chat/{conversation}', [AskController::class, 'show'])->name('chat.show');
    
    // 4. Envoyer un message (Nouvelle conversation)
    Route::post('/chat', [AskController::class, 'ask'])->name('chat.store.new');
    
    // 5. Envoyer un message (Conversation existante)
    Route::post('/chat/{conversation}', [AskController::class, 'ask'])->name('chat.store.existing');
});

// Note : Vous pouvez supprimer ces deux anciennes routes '/ask' si vous ne les 
// utilisez plus, elles font doublon avec '/chat' et risquent de créer de la confusion.
// Route::get('/ask', [AskController::class, 'index'])->name('ask.index');
// Route::post('/ask', [AskController::class, 'ask'])->name('ask.post');

require __DIR__.'/settings.php';