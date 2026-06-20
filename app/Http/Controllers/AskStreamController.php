<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\SimpleAskStreamService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AskStreamController extends Controller
{
    public function __construct(
        private SimpleAskStreamService $streamService
    ) {}

    public function index(Request $request): Response
    {
        $user = auth()->user();
        $conversations = $user->conversations()->latest()->get();

        return Inertia::render('AskStream/Index', [
            'conversations' => $conversations,
            'messages' => [],
            'models' => $this->streamService->getModelsLight(),
            'custom_instructions' => $user->custom_instructions, // ⚡ On passe la variable au frontend
        ]);
    }

    public function stream(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:100000',
            'model' => 'required|string',
            'conversation_id' => 'nullable|exists:conversations,id',
        ]);

        $user = auth()->user();

        // Gestion de la conversation
        if ($validated['conversation_id'] ?? null) {
            $conversation = $user->conversations()->findOrFail($validated['conversation_id']);
        } else {
            $conversation = $user->conversations()->create([
                'title' => str($validated['message'])->limit(25),
                'model' => $validated['model'],
            ]);
        }

        // Sauvegarde du message utilisateur
        $conversation->messages()->create([
            'role' => 'user',
            'content' => $validated['message'],
        ]);

        // Préparation de l'historique
        $history = $conversation->messages()->select('role', 'content')->oldest()->get()->toArray();
        
        // LECTURE DES INSTRUCTIONS PERSONNALISÉES 
        $defaultInstructions = "Tu es un assistant IA expert. Réponds de manière précise et utile.";
        $instructions = $user->custom_instructions ?: $defaultInstructions;
        
        // On place les instructions tout en haut de l'historique
        array_unshift($history, [
            'role' => 'system',
            'content' => $instructions
        ]);
        // -------------------------------------------------

        $model = $validated['model'];

        // Streaming
        return response()->stream(
            function () use ($history, $model, $conversation) {
                
                $this->streamService->streamToOutput($history, $model, 1.0, function(string $aiText) use ($conversation) {
                    
                    // Cette partie s'exécute automatiquement à la fin du stream
                    $conversation->messages()->create([
                        'role' => 'assistant',
                        'content' => $aiText,
                    ]);

                });
                
            },
            200,
            [
                'Content-Type' => 'text/event-stream; charset=utf-8',
                'Cache-Control' => 'no-cache, no-store',
                'X-Accel-Buffering' => 'no',
            ]
        );
    }

}