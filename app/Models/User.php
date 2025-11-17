<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Src\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\RoleModel;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'role_id',      // ← agregado
        'username',     // ← agregado
        'name',
        'email',
        'password',
        'company_id',   // ← agregado
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Relación con roles.
     */
    public function role()
    {
        return $this->belongsTo(RoleModel::class, 'role_id');
    }

    /**
     * Relación con company.
     */
    public function company()
    {
        return $this->belongsTo(\Src\Organizations\Infrastructure\Persistence\Eloquent\Models\CompanyModel::class, 'company_id');
    }

    /**
     * Relación many-to-many con locals.
     */
    public function locals()
    {
        return $this->belongsToMany(\Src\Organizations\Infrastructure\Persistence\Eloquent\Models\LocalModel::class, 'user_local', 'user_id', 'local_id')
                    ->withTimestamps();
    }

    /**
     * Helpers para validar roles.
     */
    public function hasRole(string $role): bool
    {
        return $this->role?->name === $role;
    }

    public function inAnyRole(array $roles): bool
    {
        return $this->role && in_array($this->role->name, $roles, true);
    }

    /**
     * Métodos de JWTSubject.
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [];
    }
}
