<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChatRequest;
use App\Models\Document;
use App\Models\Message;
use App\Services\RagService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use RuntimeException;

class ChatController extends Controller
{
    /**
     * Show the chat screen for a document, with its conversation so far.
     */
    public function show(Document $document): InertiaResponse
    {
        $conversation = $document->conversations()->firstOrCreate([]);

        return Inertia::render('Chat/Show', [
            'document' => $document,
            'messages' => $conversation->messages()->get(),
        ]);
    }

    /**
     * Handle a user question: run the RAG pipeline and store both messages.
     *
     *   Question -> Embedding -> Vector Search -> Relevant Chunks
     *            -> Prompt + Context -> LLM -> Answer
     */
    public function store(ChatRequest $request, Document $document, RagService $ragService): RedirectResponse
    {
        if ($document->status !== Document::STATUS_COMPLETED) {
            return back()->withErrors([
                'question' => 'This document is still processing. Please wait until it is ready.',
            ]);
        }

        $conversation = $document->conversations()->firstOrCreate([]);
        $question = $request->validated('question');

        try {
            $answer = $ragService->ask($document, $question);
        } catch (RuntimeException $e) {
            return back()->withErrors(['question' => $e->getMessage()]);
        }

        // Store the question only once it has an answer, so a failed
        // generation doesn't leave an unanswered question in the history
        // (the frontend puts it back in the input for a retry instead).
        $conversation->messages()->create([
            'role' => Message::ROLE_USER,
            'content' => $question,
        ]);

        $conversation->messages()->create([
            'role' => Message::ROLE_ASSISTANT,
            'content' => $answer,
        ]);

        return back();
    }
}
