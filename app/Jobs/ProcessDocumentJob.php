<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\ChunkingService;
use App\Services\DocumentParserService;
use App\Services\EmbeddingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Runs the document ingestion side of the RAG pipeline in the background,
 * so uploading a large PDF doesn't block the HTTP request:
 *
 *   Extract Text -> Chunk -> Embed each chunk -> Store chunks -> Mark completed
 *
 * Dispatched from DocumentController after a document is uploaded and saved
 * with status "pending". Runs on the default queue connection (configured
 * via QUEUE_CONNECTION in .env — "database" by default, so `php artisan
 * queue:work` must be running; set it to "sync" to run this immediately
 * inline instead, with no worker needed).
 */
class ProcessDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(private readonly Document $document) {}

    public function handle(
        DocumentParserService $parser,
        ChunkingService $chunker,
        EmbeddingService $embedder,
    ): void {
        $this->document->update(['status' => Document::STATUS_PROCESSING]);

        try {
            $absolutePath = Storage::disk('local')->path($this->document->file_path);

            $text = $parser->extractText($absolutePath);
            $chunks = $chunker->chunk($text);

            if (empty($chunks)) {
                throw new \RuntimeException('No extractable text was found in this PDF.');
            }

            // Embed all chunks in one batched API call rather than one per chunk.
            $embeddings = $embedder->embedBatch($chunks);

            foreach ($chunks as $index => $content) {
                $this->document->chunks()->create([
                    'chunk_index' => $index,
                    'content' => $content,
                    'embedding' => $embeddings[$index],
                ]);
            }

            $this->document->update(['status' => Document::STATUS_COMPLETED]);
        } catch (Throwable $e) {
            Log::error('Document processing failed', [
                'document_id' => $this->document->id,
                'error' => $e->getMessage(),
            ]);

            $this->document->update([
                'status' => Document::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);
        }
    }
}
