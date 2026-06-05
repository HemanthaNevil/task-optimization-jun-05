<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function documentViews(): HasMany
    {
        return $this->hasMany(\App\Models\DocumentViewsModel::class, 'user_id');
    }

    public function sentDocuments(): HasMany
    {
        return $this->hasMany(\App\Models\DocsModel::class, 'user_id');
    }

    public function signedDocuments(): HasMany
    {
        return $this->hasMany(\App\Models\Sign::class, 'user_id');
    }

    public function sharedDocuments(): HasMany
    {
        return $this->hasMany(\App\Models\ShareDocs::class, 'user_id');
    }

    public function verificationDocs(): HasMany
    {
        return $this->hasMany(\App\Models\UserVerificationDoc::class, 'user_id');
    }

    public function extraDetails(): HasOne
    {
        return $this->hasOne(\App\Models\ClientExtraDetail::class, 'user_id');
    }
}