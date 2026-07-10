<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use CrudTrait;
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;
    use HasRoles {
        hasRole as protected spatieHasRole;
        hasAnyRole as protected spatieHasAnyRole;
    }

    /**
     * Roles/permissions are stored against the "web" guard.
     */
    protected string $guard_name = 'web';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
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

    /**
     * Check if user has a specific role.
     *
     * Backwards compatible with the previous custom role system: string
     * checks match the role `slug` (e.g. 'team') in addition to Spatie's
     * name-based matching.
     */
    public function hasRole($roles, ?string $guard = null): bool
    {
        if (is_string($roles) && $this->roles->contains(fn ($role) => $role->slug === $roles)) {
            return true;
        }

        return $this->spatieHasRole($roles, $guard);
    }

    /**
     * Check if user has any of the given roles (slugs or names).
     */
    public function hasAnyRole(...$roles): bool
    {
        $flattened = collect($roles)->flatten()->all();

        foreach ($flattened as $role) {
            if (is_string($role) && $this->roles->contains(fn ($r) => $r->slug === $role)) {
                return true;
            }
        }

        return $this->spatieHasAnyRole($flattened);
    }
}
