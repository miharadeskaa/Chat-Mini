<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\SimpleAskService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;

class AskController extends Controller
{
    public function __construct(private SimpleAskService $askService) {}

    /**
     * Affiche la page principale (Nouvelle conversation).
     */
    public function index(Request $request)
    {
        $user = $request->user();

        return Inertia::render('Ask/Index', [
            'models' => $this->askService->getModels(),
            'selectedModel' => $user->model ?? $this->askService::DEFAULT_MODEL,
            'conversations' => $user->conversations()->orderByDesc('updated_at')->get(),
            'activeConversation' => null,
            'messages' => [],
        ]);
    }

    /**
     * Affiche une conversation existante.
     */
    public function show(Conversation $conversation, Request $request)
    {
        if ($conversation->user_id !== $request->user()->id) {
            abort(403, 'Accès non autorisé');
        }

        $user = $request->user();

        return Inertia::render('Ask/Index', [
            'models' => $this->askService->getModels(),
            'selectedModel' => $conversation->model ?? $user->model ?? $this->askService::DEFAULT_MODEL,
            'conversations' => $user->conversations()->orderByDesc('updated_at')->get(),
            'activeConversation' => $conversation,
            'messages' => $conversation->messages()->oldest()->get(),
        ]);
    }

    /**
     * Traite l'envoi d'un message à l'IA (Nouvelle ou Ancienne conversation).
     */
    public function ask(Request $request, ?Conversation $conversation = null)
    {
        
        if ($conversation && $conversation->user_id !== $request->user()->id) {
            abort(403, 'Accès non autorisé');
        }

        $request->validate([
            'message' => 'required|string',
            'model'   => $conversation ? 'nullable|string' : 'required|string',
        ]);

        $user = $request->user();
        $selectedModel = $request->model ?? $conversation?->model ?? $user->model;

        if ($request->model) {
            $user->update(['model' => $request->model]);
        }

        // --- COMMANDES PERSONNALISÉES ---
        $rawMessage = trim($request->message);
        $finalMessage = $rawMessage;

        $commands = [
            '/review' => "Analyse le code suivant, trouve les bugs potentiels et propose des améliorations. Format attendu : liste numérotée par priorité de résolution.\n\nCode :\n",
            '/eli5'   => "Explique le concept suivant de manière ultra simple, avec une analogie du quotidien :\n\n",
            '/commit' => "Génère un message de commit conventionnel pour les changements décrits ci-dessous :\n\n",
            '/résumé' => "Fais un résumé clair et structuré (avec des puces) du texte suivant :\n\n"
        ];

        if (str_starts_with($rawMessage, '/')) {
            $parts = explode(' ', $rawMessage, 2);
            $command = $parts[0]; 
            $userText = $parts[1] ?? ''; 

            if (array_key_exists($command, $commands)) {
                $finalMessage = $commands[$command] . $userText;
            }
        }
        // ----------------------------------------

        if (!$conversation) {
            $computedTitle = Str::limit($rawMessage, 15, '...');

            $conversation = $user->conversations()->create([
                'title' => $computedTitle,          
                'model' => $selectedModel,
            ]);
        } else {
            if ($request->model) {
                $conversation->update(['model' => $request->model]);
            }
            $conversation->touch(); 
        }

        $conversation->messages()->create([
            'role' => 'user',
            'content' => $finalMessage,
        ]);

        $history = $conversation->messages()->select('role', 'content')->oldest()->get()->toArray();

        // --- PROFILS ET COMPORTEMENTS ---

        // 1. On définit un comportement par défaut si l'utilisateur n'a rien configuré
        $defaultInstructions = "Tu es un assistant IA expert. L'utilisateur est un développeur web PHP/Laravel avec 5 ans d'expérience. 
            Règles de comportement : 
            1. Ton professionnel, direct et sans blabla.
            2. Code-first : donne la solution ou le code immédiatement, puis explique brièvement en dessous si nécessaire.
            3. Utilise des listes à puces pour structurer tes réponses longues.";
        
        // 2. On récupère les instructions de la BDD, ou on utilise le défaut
        $instructions = $user->custom_instructions ?: $defaultInstructions;
        
        // On donne les instructions à l'IA
        $systemPrompt = [
            'role' => 'system',
            'content' => $instructions 
        ];
        
        array_unshift($history, $systemPrompt);
        // -------------------------------------------

        try {
            $response = $this->askService->sendMessage(
                messages: $history,
                model: $selectedModel
            );

            $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $response,
            ]);

        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return redirect('/chat/' . $conversation->id);
    } 

    /**
     * Met à jour le modèle sélectionné.
     */
    public function updateModel(Request $request)
    {
        $request->validate([
            'model' => 'required|string',
            'conversation_id' => 'nullable|exists:conversations,id', 
        ]);

        $user = $request->user();

        $user->update(['model' => $request->model]);

        if ($request->conversation_id) {
            $user->conversations()->where('id', $request->conversation_id)->update(['model' => $request->model]);
        }

        return back();
    }

    /** * Supprimer une conversation et son historique 
     */
    public function destroy(Conversation $conversation, Request $request)
    {
        // Sécurité
        if ($conversation->user_id !== $request->user()->id) {
            abort(403, 'Accès non autorisé');
        }

        $conversation->delete();

        return redirect('/chat')->with('success', 'Conversation supprimée.');
    }

    /**
     * Met à jour les instructions personnalisées.
     */
    public function updateInstructions(Request $request)
    {
        $request->validate([
            'custom_instructions' => 'nullable|string|max:3000',
        ]);

        $request->user()->update([
            'custom_instructions' => $request->custom_instructions
        ]);

        return back()->with('success', 'Instructions mises à jour !');
    }

    /**
     * Bascule entre le mode clair et sombre
     */
    public function toggleTheme(Request $request)
    {
        $user = $request->user();
        
        // Si c'est light on passe en dark, et inversement
        $newTheme = $user->theme === 'dark' ? 'light' : 'dark';
        
        $user->update(['theme' => $newTheme]);

        return back();
    }
}