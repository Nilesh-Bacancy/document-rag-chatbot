<?php

namespace App\Services;

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

        $model = config('services.gemini.chat_model');

        $response = Http::withHeader('x-goog-api-key', $apiKey)
            ->timeout(60)
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

        if ($response->failed()) {
            Log::error('Gemini generateContent request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException('Failed to generate an answer. Please try again later.');
        }

        return trim((string) $response->json('candidates.0.content.parts.0.text'));
    }
}
