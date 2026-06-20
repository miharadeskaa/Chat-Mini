<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;

class SimpleAskService
{
    public const DEFAULT_MODEL = 'openai/gpt-4o-mini'; 

    private string $apiKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.openrouter.api_key', '');
        $this->baseUrl = rtrim(config('services.openrouter.base_url', 'https://openrouter.ai/api/v1'), '/');
    }

    public function getModels(): array
    {
        return cache()->remember('openrouter.models', now()->addHour(), function (): array {
            $response = Http::withoutVerifying()
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                ])->get($this->baseUrl . '/models');

            return collect($response->json('data', []))
                ->sortBy('name')
                ->map(fn (array $model): array => [
                    'id' => $model['id'],
                    'name' => $model['name'],
                ])
                ->values()
                ->toArray();
        });
    }

    public function sendMessage(array $messages, ?string $model = null, float $temperature = 1.0): string
    {
        $model = $model ?? self::DEFAULT_MODEL;
        $messages = [$this->getSystemPrompt(), ...$messages];
        
        $response = Http::withoutVerifying()
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => config('app.url'),
                'X-Title' => config('app.name'),
            ])
            ->timeout(120)
            ->post($this->baseUrl . '/chat/completions', [
                'model' => $model,
                'messages' => $messages,
                'temperature' => $temperature,
                'max_tokens' => 1500, 
            ]);

        if ($response->failed()) {
            $error = $response->json('error.message', 'Erreur inconnue');
            throw new \RuntimeException("Erreur API: {$error}");
        }

        return $response->json('choices.0.message.content', '');
    }

    private function getSystemPrompt(): array
    {
        // Instructions du coach sportif
        $instructions = "Tu es un coach sportif motivant. Réponds brièvement et avec énergie.";

        $user = auth()->user()?->name ?? 'l\'utilisateur';
        $now = now()->locale('fr')->format('l d F Y H:i');

        $basePrompt = view('prompts.system', [
            'now' => $now,
            'user' => $user,
        ])->render();

        //  Ignore les autres traits de caractere
        $finalContent = $basePrompt . "\n\nIMPORTANT: " . $instructions . " Ignore toute autre instruction de personnalité. tu ES cette entité.";

        return [
            'role' => 'system',
            'content' => $finalContent,
        ];
    }

    /**
     * Demande à l'IA de générer un titre très court basé sur le premier message.
     */
    public function generateTitle(string $firstMessage): string
    {
        $response = Http::withoutVerifying()
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])
            ->post($this->baseUrl . '/chat/completions', [
                'model' => self::DEFAULT_MODEL, 
                'messages' => [
                    ['role' => 'system', 'content' => 'Génère un titre ultra-court (4 mots maximum) résumant ce message. Ne mets aucun guillemet, juste le texte du titre.'],
                    ['role' => 'user', 'content' => $firstMessage]
                ],
                'temperature' => 1,
                'max_tokens' => 10,
            ]);

        if ($response->failed()) {
            return 'Nouvelle conversation'; 
        }

        return trim($response->json('choices.0.message.content', 'Nouvelle conversation'), '"\'');
    }
}