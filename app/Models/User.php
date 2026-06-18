<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'role',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
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
            'is_admin' => 'boolean',
        ];
    }

    /**
     * Check if user is admin (gate for accessing /admin).
     */
    public function isAdmin(): bool
    {
        return $this->is_admin ?? false;
    }

    /**
     * Check if user is a super-admin (full access).
     * Falls back to is_admin for legacy rows where role is null.
     */
    public function isSuperAdmin(): bool
    {
        if ($this->role === 'superadmin') {
            return true;
        }

        // Compat : un is_admin sans rôle défini reste super-admin.
        return $this->is_admin && $this->role === null;
    }

    /**
     * Check if user is a moderator (limited admin).
     */
    public function isModerator(): bool
    {
        return $this->role === 'moderator';
    }
}
