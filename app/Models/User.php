<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_SUPER_ADMIN = 'super_admin';
    public const ROLE_ADMIN = 'admin';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'last_login_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_SUPER_ADMIN], true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function createdFaqs(): HasMany
    {
        return $this->hasMany(Faq::class, 'created_by');
    }

    public function updatedFaqs(): HasMany
    {
        return $this->hasMany(Faq::class, 'updated_by');
    }

    public function uploadedDocuments(): HasMany
    {
        return $this->hasMany(KnowledgeDocument::class, 'uploaded_by');
    }

    public function evaluatedMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'evaluated_by');
    }

    public function updatedSettings(): HasMany
    {
        return $this->hasMany(ChatbotSetting::class, 'updated_by');
    }
}
