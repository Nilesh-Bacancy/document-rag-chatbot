<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * RAG step 6: Generation.
 *
 * Sends a prompt (built by RagService) to Google's Gemini generateContent
 * API and returns the generated answer text. This class knows nothing about
 * documents, chunks, or embeddings — it's a thin wrapper around "send a
 * prompt, get an answer back", which keeps it reusable and easy to test.
 */
class LlmService
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

    /**
     * Send a system + user prompt to the LLM and return its answer text.
     */
    public function generate(string $systemPrompt, string $userPrompt): string
    {
        $apiKey = config('services.gemini.api_key');

        if (empty($apiKey)) {
            throw new RuntimeException('GEMINI_API_KEY is not configured.');
        }

        $models = array_unique(array_filter([
            config('services.gemini.chat_model'),
            config('services.gemini.chat_fallback_model'),
        ]));

        foreach ($models as $model) {
            $response = $this->request($apiKey, $model, $systemPrompt, $userPrompt);

            if ($response->successful()) {
                return trim((string) $response->json('candidates.0.content.parts.0.text'));
            }

            Log::error('Gemini generateContent request failed', [
                'model' => $model,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            // Only an overloaded/rate-limited model is worth falling back
            // from; anything else (bad key, bad request) will fail the same
            // way on every model.
            if (! $this->isTransient($response->status())) {
                break;
            }
        }

        throw new RuntimeException('Failed to generate an answer. Please try again later.');
    }

    /**
     * Call generateContent on one model, retrying briefly when Gemini is
     * overloaded (these 503s usually clear within a few seconds).
     */
    private function request(string $apiKey, string $model, string $systemPrompt, string $userPrompt): Response
    {
        return Http::withHeader('x-goog-api-key', $apiKey)
            ->timeout(60)
            ->retry(3, 1000, fn ($e) => $e instanceof RequestException && $this->isTransient($e->response->status()), throw: false)
            ->post(sprintf(self::ENDPOINT, $model), [
                'systemInstruction' => [
                    'parts' => [['text' => $systemPrompt]],
                ],
                'contents' => [
                    ['role' => 'user', 'parts' => [['text' => $userPrompt]]],
                ],
                'generationConfig' => [
                    'temperature' => 0.2,
                ],
            ]);
    }

    private function isTransient(int $status): bool
    {
        return $status === 429 || $status >= 500;
    }
}
