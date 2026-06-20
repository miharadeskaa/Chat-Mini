<?php

declare(strict_types=1);

namespace App\Services;

use Generator;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\StreamInterface;

class SimpleAskStreamService
{
    public const DEFAULT_MODEL = 'openai/gpt-4o-mini';
    private string $apiKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.openrouter.api_key');
        $this->baseUrl = rtrim(config('services.openrouter.base_url', 'https://openrouter.ai/api/v1'), '/');
    }

    // Instructions
    private function getSystemPrompt(): array
    {
        $instructions = "Tu es un coach sportif motivant. Réponds brièvement et avec énergie.";

        // 1. Générer le texte à partir de la vue
        $basePrompt = view('prompts.system', [
            'now' => now()->locale('fr')->format('l d F Y H:i'),
            'user' => auth()->user()?->name ?? 'l\'utilisateur',
        ])->render();

        // 2. Combiner le rendu de la vue avec les instructions strictes
        $finalContent = $basePrompt . "\n\nIMPORTANT: " . $instructions . " Ignore toute autre instruction de personnalité. tu ES cette entité.";

        // 3. Faire un seul retour
        return [
            'role' => 'system',
            'content' => $finalContent,
        ];
    }

    // Ajout des paramètres
    public function streamToOutput(array $messages, ?string $model, float $temperature = 1.0, ?callable $onComplete = null): void 
    {
        $fullMessages = [$this->getSystemPrompt(), ...$messages];

        $payload = [
            'model' => $model ?? self::DEFAULT_MODEL,
            'messages' => $fullMessages,
            'temperature' => $temperature,
            'stream' => true,
            'max_tokens' => 2000,
        ];

        $response = Http::withToken($this->apiKey)
            ->withHeaders(['HTTP-Referer' => config('app.url'), 'X-Title' => config('app.name')])
            ->withOptions(['stream' => true])
            ->timeout(120)
            ->post("{$this->baseUrl}/chat/completions", $payload);

        // On crée la variable
        $fullAiResponse = '';

        foreach ($this->parseSSEStream($response->toPsrResponse()->getBody()) as $event) {
            if ($event['type'] === 'content') {

                // On AJOUTE chaque mot généré à notre variable
                $fullAiResponse .= $event['data'];
                echo $event['data'];
            }
            
            elseif ($event['type'] === 'reasoning') {
                echo "[REASONING]" . $event['data'] . "[/REASONING]";
            }
            
            if (ob_get_level() > 0) ob_flush();
            flush();
        }

        // on sauvegarde le texte complet !
        if ($onComplete !== null) {
            $onComplete($fullAiResponse);
        }
    }

    private function parseSSEStream(StreamInterface $body): Generator
    {
        $buffer = '';
        while (!$body->eof()) {
            $buffer .= $body->read(1024);
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $pos));
                $buffer = substr($buffer, $pos + 1);
                
                if (str_starts_with($line, 'data: ')) {
                    $data = substr($line, 6);
                    if ($data === '[DONE]') break;
                    
                    $parsed = json_decode($data, true);
                    $delta = $parsed['choices'][0]['delta'] ?? [];
                    
                    
                    if (isset($delta['content']) && $delta['content'] !== '') {
                        yield ['type' => 'content', 'data' => $delta['content']];
                    }
                    if (isset($delta['reasoning_content']) && $delta['reasoning_content'] !== '') {
                        yield ['type' => 'reasoning', 'data' => $delta['reasoning_content']];
                    }
                }
            }
        }
    }

    public function getModelsLight(): array
    {
        return cache()->remember('openrouter.models.light', now()->addHour(), function () {
            $response = Http::withToken($this->apiKey)->get("{$this->baseUrl}/models");
            return collect($response->json('data', []))->map(fn($m) => ['id' => $m['id'], 'name' => $m['name']])->toArray();
        });
    }
}