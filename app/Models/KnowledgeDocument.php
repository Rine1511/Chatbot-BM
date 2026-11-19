<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeDocument extends Model
{
    public const SOURCE_TEXT = 'text';
    public const SOURCE_FILE = 'file';
    public const SOURCE_SIBBM = 'sib_bm_sync';

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_INDEXED = 'indexed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'category_id',
        'title',
        'description',
        'source_type',
        'original_filename',
        'file_path',
        'mime_type',
        'file_size',
        'raw_content',
        'source_label',
        'source_url',
        'processing_status',
        'is_active',
        'error_message',
        'chunk_count',
        'last_indexed_at',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'chunk_count' => 'integer',
            'is_active' => 'boolean',
            'last_indexed_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FaqCategory::class, 'category_id');
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(KnowledgeChunk::class, 'document_id')->orderBy('chunk_index');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function formattedFileSize(): string
    {
        if (! $this->file_size) {
            return '-';
        }

        if ($this->file_size < 1024) {
            return $this->file_size.' B';
        }

        if ($this->file_size < 1048576) {
            return number_format($this->file_size / 1024, 1).' KB';
        }

        return number_format($this->file_size / 1048576, 2).' MB';
    }
}
