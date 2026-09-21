<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * RAG step 6: Generation.
 *
 * Sends a prompt (built by RagService) to OpenAI's Chat Completions API and
 * returns the generated answer text. This class knows nothing about
 * documents, chunks, or embeddings — it's a thin wrapper around "send
 * messages, get an answer back", which keeps it reusable and easy to test.
 */
class LlmService
{
    private const ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    /**
     * Send a system + user prompt to the LLM and return its answer text.
     */
    public function generate(string $systemPrompt, string $userPrompt): string
    {
        $apiKey = config('services.openai.api_key');

        if (empty($apiKey)) {
            throw new RuntimeException('OPENAI_API_KEY is not configured.');
        }

        $response = Http::withToken($apiKey)
            ->timeout(60)
            ->post(self::ENDPOINT, [
                'model' => config('services.openai.chat_model'),
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => 0.2,
            ]);

        if ($response->failed()) {
            Log::error('OpenAI chat completion request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException('Failed to generate an answer. Please try again later.');
        }

        return trim((string) $response->json('choices.0.message.content'));
    }
}
