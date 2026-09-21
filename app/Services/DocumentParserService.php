<?php

namespace App\Services;

use Smalot\PdfParser\Parser;

/**
 * RAG step 1: Extract text.
 *
 * A PDF is a binary format; before we can do anything useful with a document
 * (chunk it, embed it, search it) we need its plain text content.
 */
class DocumentParserService
{
    /**
     * Extract the full plain-text content of a PDF file.
     */
    public function extractText(string $absolutePath): string
    {
        $parser = new Parser;
        $pdf = $parser->parseFile($absolutePath);

        $text = $pdf->getText();

        // Normalise excessive blank lines/whitespace left behind by the parser.
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }
}
