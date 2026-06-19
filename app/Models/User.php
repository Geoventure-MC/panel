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
     *
     * Principe ANTI-LOCKOUT : seul un compte explicitement « moderator » est
     * restreint. Tout autre admin (role 'superadmin', valeur par défaut 'admin',
     * ou role null sur d'anciennes lignes) garde l'accès complet — sinon un
     * compte resté à la valeur par défaut 'admin' perdrait Users/Config/MAJ.
     */
    public function isSuperAdmin(): bool
    {
        if (!$this->is_admin) {
            return false;
        }

        return $this->role !== 'moderator';
    }

    /**
     * Check if user is a moderator (limited admin).
     */
    public function isModerator(): bool
    {
        return $this->role === 'moderator';
    }
}
