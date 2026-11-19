<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeEmbedding extends Model
{
    protected $fillable = [
        'chunk_id',
        'embedding_model',
        'dimensions',
        'vector_data',
    ];

    protected function casts(): array
    {
        return [
            'dimensions' => 'integer',
            'vector_data' => 'array',
        ];
    }

    public function chunk(): BelongsTo
    {
        return $this->belongsTo(KnowledgeChunk::class, 'chunk_id');
    }
}
