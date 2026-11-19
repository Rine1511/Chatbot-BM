<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatbotSetting extends Model
{
    protected $fillable = [
        'setting_key',
        'setting_value',
        'setting_group',
        'is_encrypted',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_encrypted' => 'boolean',
        ];
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $setting = static::query()->where('setting_key', $key)->first();

        return $setting ? $setting->setting_value : $default;
    }

    public static function setValue(
        string $key,
        ?string $value,
        string $group = 'general',
        ?int $updatedBy = null
    ): self {
        return static::query()->updateOrCreate(
            ['setting_key' => $key],
            [
                'setting_value' => $value,
                'setting_group' => $group,
                'updated_by' => $updatedBy,
            ]
        );
    }
}
