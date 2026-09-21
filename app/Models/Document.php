<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'name',
        'original_filename',
        'file_path',
        'status',
        'error_message',
    ];

    /**
     * The chunks this document was split into for retrieval.
     */
    public function chunks(): HasMany
    {
        return $this->hasMany(DocumentChunk::class)->orderBy('chunk_index');
    }

    /**
     * The chat conversations held about this document.
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }
}
