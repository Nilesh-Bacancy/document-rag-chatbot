<?php

namespace App\Services;

/**
 * RAG step 2: Chunk.
 *
 * LLMs and embedding models work best on small, focused pieces of text
 * rather than an entire document at once. We split the extracted text into
 * overlapping chunks so that:
 *   - each chunk is small enough to embed and to fit in a prompt, and
 *   - the overlap avoids cutting a sentence/idea in half between chunks.
 *
 * This is intentionally a simple, fixed-size character splitter. A more
 * advanced version could split on sentences/paragraphs or use a tokenizer,
 * but that isn't necessary to demonstrate the RAG pipeline.
 */
class ChunkingService
{
    /**
     * Split text into a list of overlapping chunks.
     *
     * @return array<int, string>
     */
    public function chunk(string $text, int $chunkSize = 1000, int $overlap = 200): array
    {
        $text = trim($text);

        if ($text === '') {
            return [];
        }

        $chunks = [];
        $length = mb_strlen($text);
        $start = 0;

        while ($start < $length) {
            $end = min($start + $chunkSize, $length);
            $piece = mb_substr($text, $start, $end - $start);

            // Try not to cut a word in half: back off to the last whitespace
            // unless we've already reached the end of the text.
            if ($end < $length) {
                $lastSpace = mb_strrpos($piece, ' ');
                if ($lastSpace !== false && $lastSpace > 0) {
                    $piece = mb_substr($piece, 0, $lastSpace);
                }
            }

            $piece = trim($piece);
            if ($piece !== '') {
                $chunks[] = $piece;
            }

            // We've consumed the rest of the text: stop, otherwise the
            // overlap would make us re-emit shrinking tail fragments forever.
            if ($end >= $length) {
                break;
            }

            $advance = max(mb_strlen($piece), 1);
            $start += max($advance - $overlap, 1);
        }

        return $chunks;
    }
}
