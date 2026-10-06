<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * @property int $user_id
 * @property string $full_name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password_hash
 * @property string $status
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'password_hash', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The primary key associated with the LMS identity schema.
     */
    protected $primaryKey = 'user_id';

    /**
     * Virtual starter-kit attributes included in serialized users.
     *
     * @var list<string>
     */
    protected $appends = ['id', 'name'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password_hash' => 'hashed',
        ];
    }

    /**
     * Keep the starter kit's public `id` contract while using `user_id` in storage.
     *
     * @return Attribute<int, never>
     */
    protected function id(): Attribute
    {
        return Attribute::get(fn (): mixed => $this->getKey());
    }

    /**
     * Map the starter kit's name field to the LMS `full_name` column.
     *
     * @return Attribute<string, string>
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn (): string => (string) $this->getAttribute('full_name'),
            set: fn (string $value): array => ['full_name' => $value],
        );
    }

    /**
     * Map password writes from Fortify to the LMS `password_hash` column.
     *
     * @return Attribute<string, string>
     */
    protected function password(): Attribute
    {
        return Attribute::make(
            get: fn (): string => (string) $this->getAttribute('password_hash'),
            set: fn (string $value): array => [
                'password_hash' => Hash::needsRehash($value) ? Hash::make($value) : $value,
            ],
        );
    }

    /**
     * Return the password column used by Laravel's user provider.
     */
    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    /** @return BelongsToMany<Role, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id')
            ->withPivot('granted_at');
    }

    public function hasRole(string $roleCode): bool
    {
        return $this->roles()->where('role_code', $roleCode)->exists();
    }

    public function hasPermission(string $permissionCode): bool
    {
        return $this->roles()
            ->whereHas('permissions', fn ($query) => $query->where('permission_code', $permissionCode))
            ->exists();
    }
}
