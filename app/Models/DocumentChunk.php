<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentChunk extends Model
{
    protected $fillable = [
        'document_id',
        'chunk_index',
        'content',
        'embedding',
    ];

    /**
     * Cast the embedding vector to/from a plain PHP array.
     */
    protected function casts(): array
    {
        return [
            'embedding' => 'array',
        ];
    }

    /**
     * The document this chunk was extracted from.
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
