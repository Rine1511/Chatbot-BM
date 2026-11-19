<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Faq extends Model
{
    public const EMBEDDING_PENDING = 'pending';
    public const EMBEDDING_INDEXED = 'indexed';
    public const EMBEDDING_FAILED = 'failed';

    protected $fillable = [
        'category_id',
        'question',
        'answer',
        'keywords',
        'question_variations',
        'source_url',
        'is_featured',
        'is_active',
        'embedding_status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FaqCategory::class, 'category_id');
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(KnowledgeChunk::class, 'faq_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope khusus sumber jawaban chatbot:
     * hanya FAQ aktif yang berada pada kategori aktif.
     */
    public function scopeForChatbot(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->whereHas('category', fn (Builder $q) => $q->where('is_active', true));
    }

    /**
     * @return list<string>
     */
    public function variationsList(): array
    {
        if (! $this->question_variations) {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', $this->question_variations) ?: [];

        return array_values(array_filter(array_map('trim', $lines), fn (string $v) => $v !== ''));
    }
}
