<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class KnowledgeChunk extends Model
{
    protected $fillable = [
        'document_id',
        'faq_id',
        'chunk_index',
        'content',
        'token_count',
        'content_hash',
        'metadata',
        'vector_point_id',
    ];

    protected function casts(): array
    {
        return [
            'chunk_index' => 'integer',
            'token_count' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(KnowledgeDocument::class, 'document_id');
    }

    public function faq(): BelongsTo
    {
        return $this->belongsTo(Faq::class, 'faq_id');
    }

    public function embedding(): HasOne
    {
        return $this->hasOne(KnowledgeEmbedding::class, 'chunk_id');
    }
}
