<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\SimpleAskService;
use Illuminate\Http\Request;
use Inertia\Inertia;

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
            // On récupère le modèle préféré de l'utilisateur en BDD
            'selectedModel' => $user->model ?? $this->askService::DEFAULT_MODEL,
            // On liste l'historique des conversations pour la barre latérale
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
        // Sécurité : on vérifie que la conversation appartient bien à l'utilisateur
        if ($conversation->user_id !== $request->user()->id) {
            abort(403);
        }

        $user = $request->user();

        return Inertia::render('Ask/Index', [
            'models' => $this->askService->getModels(),
            // Le modèle de la conversation, sinon celui du user, sinon le défaut
            'selectedModel' => $conversation->model ?? $user->model ?? $this->askService::DEFAULT_MODEL,
            'conversations' => $user->conversations()->orderByDesc('updated_at')->get(),
            'activeConversation' => $conversation,
            // On charge l'historique de cette discussion précise
            'messages' => $conversation->messages()->oldest()->get(),
        ]);
    }

    /**
     * Traite l'envoi d'un message à l'IA (Nouvelle ou Ancienne conversation).
     */
    public function ask(Request $request, ?Conversation $conversation = null)
    {
        $request->validate([
            'message' => 'required|string',
            'model' => 'required|string',
        ]);

        $user = $request->user();

        // 1. Sauvegarde du modèle par défaut pour l'utilisateur
        $user->update(['model' => $request->model]);

        // 2. Si c'est le tout premier message, on crée la conversation en base
        if (!$conversation) {
            $conversation = $user->conversations()->create([
                'title' => 'Nouvelle conversation',
                'model' => $request->model,
            ]);
        } else {
            // Si elle existe, on met à jour son modèle et son "updated_at"
            $conversation->update(['model' => $request->model]);
            $conversation->touch(); 
        }

        // 3. On sauvegarde le message de l'utilisateur
        $conversation->messages()->create([
            'role' => 'user',
            'content' => $request->message,
        ]);

        // 4. On récupère tout l'historique pour donner du contexte à l'IA
        $history = $conversation->messages()->select('role', 'content')->oldest()->get()->toArray();

        // ---- NOUVEAU CODE : INJECTION DU COACH SPORTIF ----
        $systemPrompt = [
            'role' => 'system',
            'content' => "Tu es un coach sportif virtuel expert en fitness, musculation et nutrition. Ton ton est motivant, énergique et bienveillant, tu tutoies l'utilisateur. Règle absolue : Tu ne dois répondre qu'aux questions liées au sport, à l'entraînement physique et à l'alimentation. Si l'utilisateur te pose une question sur un tout autre sujet (comme l'informatique, la politique, etc.), tu dois refuser poliment de répondre et le recadrer sur le sport."
        ];
        
        // La fonction array_unshift ajoute notre message système tout au début du tableau
        array_unshift($history, $systemPrompt);
        // ---------------------------------------------------

        try {
            // 5. Appel à l'API (OpenRouter) avec l'historique modifié
            $response = $this->askService->sendMessage(
                messages: $history,
                model: $request->model
            );

            // 6. On sauvegarde la réponse de l'IA
            $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $response,
            ]);

            
            // Si c'est la première interaction (2 messages : user + assistant)
            if ($conversation->messages()->count() === 2) {
                $titlePrompt = [
                    ['role' => 'system', 'content' => 'Tu es un assistant qui génère des titres. Résume la question de l\'utilisateur en un titre ultra court (maximum 4 mots). Ne renvoie QUE le titre, sans guillemets ni ponctuation.'],
                    ['role' => 'user', 'content' => $request->message],
                ];
                
                $title = $this->askService->sendMessage($titlePrompt, $request->model);
                $conversation->update(['title' => trim($title)]);
            }

        } catch (\Exception $e) {
            // Si l'API plante, on renvoie l'erreur au front-end
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        // On redirige vers l'URL spécifique de cette conversation
        return redirect('/chat/' . $conversation->id);
    }

    /**
     * Met à jour le modèle sélectionné (appelé lors du changement dans le menu déroulant).
     */
    public function updateModel(Request $request)
    {
        $request->validate([
            'model' => 'required|string',
            'conversation_id' => 'nullable|exists:conversations,id', 
        ]);

        $user = $request->user();

        // On sauvegarde en BDD (table users)
        $user->update(['model' => $request->model]);

        // On met à jour la conversation si elle est active
        if ($request->conversation_id) {
            $user->conversations()->where('id', $request->conversation_id)->update(['model' => $request->model]);
        }

        return back();
    }
}