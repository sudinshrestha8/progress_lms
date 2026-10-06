<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    public const UPDATED_AT = null;

    protected $primaryKey = 'permission_id';

    /** @var list<string> */
    protected $fillable = ['permission_code', 'module', 'description'];

    /** @return BelongsToMany<Role, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permissions', 'permission_id', 'role_id')
            ->withPivot('created_at');
    }
}
