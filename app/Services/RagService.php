<?php

namespace App\Services;

use App\Models\Document;

/**
 * Orchestrates the full RAG (Retrieval-Augmented Generation) flow for
 * answering one question about one document:
 *
 *   Question -> Embedding -> Vector Search -> Relevant Chunks
 *            -> Prompt + Context -> LLM -> Answer
 *
 * "Retrieval" is finding the relevant chunks (steps 1-3 below).
 * "Augmentation" is inserting those chunks into the prompt (step 4).
 * "Generation" is the LLM producing the final answer (step 5).
 */
class RagService
{
    public function __construct(
        private readonly EmbeddingService $embeddingService,
        private readonly VectorSearchService $vectorSearchService,
        private readonly LlmService $llmService,
    ) {}

    /**
     * Answer a question about a document, grounded only in that document's content.
     */
    public function ask(Document $document, string $question, int $topN = 5): string
    {
        // 1-2. Turn the question into an embedding and search for similar chunks.
        $questionEmbedding = $this->embeddingService->embed($question);
        $relevantChunks = $this->vectorSearchService->search($document, $questionEmbedding, $topN);

        if ($relevantChunks->isEmpty()) {
            return "I couldn't find anything related to that in the uploaded document.";
        }

        // 3-4. Augment: build the context block from the retrieved chunks.
        $context = $relevantChunks
            ->map(fn ($chunk) => $chunk->content)
            ->implode("\n\n---\n\n");

        $systemPrompt = <<<'PROMPT'
            You are a document assistant. Answer the user's question using ONLY
            the provided document context below. Do not use any outside knowledge.

            If the context does not contain the answer (for example, the question
            is about a topic the document doesn't cover), do not guess or answer
            from general knowledge. Instead reply in this form:
            "This document doesn't contain any information about <topic>." followed
            by one short sentence saying what the document does cover, so the
            user knows what they can ask.

            If the message is a greeting or small talk, reply briefly and invite
            the user to ask a question about the document.

            Write in plain text: no Markdown symbols such as **, __ or #. Use
            simple numbered or "-" lists where a list helps.
            PROMPT;

        $userPrompt = <<<PROMPT
            Document context:
            {$context}

            Question:
            {$question}
            PROMPT;

        // 5. Generate: ask the LLM to answer using only that context.
        return $this->llmService->generate($systemPrompt, $userPrompt);
    }
}
