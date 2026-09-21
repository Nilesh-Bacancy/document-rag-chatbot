<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * RAG step 3: Embedding.
 *
 * An embedding is a numerical representation of text meaning: a list of a
 * few thousand floating point numbers (a "vector"). Text with similar
 * meaning ends up with vectors that are close together in that vector
 * space. This is what lets us find "relevant" chunks later by comparing
 * numbers instead of matching keywords.
 *
 * This service calls Google's Gemini Embeddings API to turn a piece of text
 * into its vector. It's used both for document chunks (once, when the
 * document is processed) and for the user's question (once per question,
 * at search time).
 */
class EmbeddingService
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/%s:batchEmbedContents';

    /**
     * Convert a single piece of text into its embedding vector.
     *
     * @return array<int, float>
     */
    public function embed(string $text): array
    {
        return $this->embedBatch([$text])[0];
    }

    /**
     * Convert multiple pieces of text into embedding vectors in one API call.
     * Batching is both faster and cheaper than calling the API per chunk.
     *
     * @param  array<int, string>  $texts
     * @return array<int, array<int, float>>
     */
    public function embedBatch(array $texts): array
    {
        $apiKey = config('services.gemini.api_key');

        if (empty($apiKey)) {
            throw new RuntimeException('GEMINI_API_KEY is not configured.');
        }

        $model = config('services.gemini.embedding_model');

        $requests = array_map(fn (string $text) => [
            'model' => "models/{$model}",
            'content' => ['parts' => [['text' => $text]]],
        ], $texts);

        $response = Http::withHeader('x-goog-api-key', $apiKey)
            ->timeout(60)
            ->post(sprintf(self::ENDPOINT, $model), [
                'requests' => $requests,
            ]);

        if ($response->failed()) {
            Log::error('Gemini embeddings request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException('Failed to generate embeddings. Please try again later.');
        }

        // The API returns embeddings in the same order as the input requests.
        return collect($response->json('embeddings'))
            ->pluck('values')
            ->all();
    }
}
