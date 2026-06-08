<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\SimpleAskService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ChatController extends Controller
{
    public function __construct(private SimpleAskService $askService) {}

    /**
     * Affiche l'interface de chat et charge l'historique.
     */
    public function index(?Conversation $conversation = null)
    {
        $user = auth()->user();

        // Sécurité : Vérifier que la conversation appartient bien à l'utilisateur connecté
        if ($conversation && $conversation->user_id !== $user->id) {
            abort(403, 'Accès refusé. Cette conversation ne vous appartient pas.');
        }

        // Récupérer toutes les conversations (les plus récentes en haut)
        $conversations = $user->conversations()->orderBy('updated_at', 'desc')->get();

        // Charger les messages si une conversation est sélectionnée
        $messages = $conversation ? $conversation->messages()->orderBy('created_at', 'asc')->get() : [];

        // Déterminer le modèle à utiliser (celui de la conversation, ou celui par défaut du user)
        $selectedModel = $conversation 
            ? $conversation->model 
            : ($user->default_model ?? SimpleAskService::DEFAULT_MODEL);

        return Inertia::render('Chat/Index', [
            'conversations' => $conversations,
            'activeConversation' => $conversation,
            'messages' => $messages,
            'models' => $this->askService->getModels(),
            'selectedModel' => $selectedModel,
            'flash' => session('error') ? ['error' => session('error')] : null,
        ]);
    }

    /**
     * Traite l'envoi d'un nouveau message.
     */
    public function store(Request $request, ?Conversation $conversation = null)
    {
        $request->validate([
            'message' => 'required|string',
            'model' => 'required|string',
        ]);

        $user = auth()->user();

        // 1. Créer la conversation si on est sur une nouvelle discussion
        if (!$conversation) {
            $conversation = $user->conversations()->create([
                'model' => $request->model,
                'title' => 'Nouvelle conversation', // Titre temporaire
            ]);
            
            // On demande à l'IA de générer un vrai titre
            $title = $this->askService->generateTitle($request->message);
            $conversation->update(['title' => $title]);
        }

        // 2. Sauvegarder la question de l'utilisateur en base de données
        $conversation->messages()->create([
            'role' => 'user',
            'content' => $request->message,
        ]);

        // Mettre à jour l'heure de la conversation pour la faire remonter dans le menu
        $conversation->touch();

        // 3. Préparer tout l'historique pour l'envoyer à l'IA
        $history = $conversation->messages()->orderBy('created_at', 'asc')->get()->map(function ($msg) {
            return [
                'role' => $msg->role,
                'content' => $msg->content,
            ];
        })->toArray();

        // 4. Interroger l'IA et sauvegarder sa réponse
        try {
            $aiResponse = $this->askService->sendMessage($history, $conversation->model);

            $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $aiResponse,
            ]);
        } catch (\Exception $e) {
            // Si l'API plante, on renvoie l'erreur à la vue
            return back()->with('error', $e->getMessage());
        }

        // 5. On redirige l'utilisateur vers l'URL de cette conversation précise
        return redirect()->route('chat.index', $conversation->id);
    }

    /**
     * Met à jour le modèle par défaut de l'utilisateur (et de la conversation en cours).
     */
    public function updateModel(Request $request)
    {
        $request->validate([
            'model' => 'required|string',
            'conversation_id' => 'nullable|exists:conversations,id',
        ]);

        $user = auth()->user();

        // On sauvegarde le choix dans la table users
        $user->update(['default_model' => $request->model]);

        // Si une conversation est ouverte, on change aussi son modèle
        if ($request->conversation_id) {
            $conversation = Conversation::where('id', $request->conversation_id)
                ->where('user_id', $user->id)
                ->first();
            
            if ($conversation) {
                $conversation->update(['model' => $request->model]);
            }
        }

        return back(); // Recharge la page de manière transparente
    }
}