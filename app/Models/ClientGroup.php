<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientGroup extends Model
{
    use SoftDeletes;

    public const CREATED_AT = 'createdAt';
    public const UPDATED_AT = 'updatedAt';
    public const DELETED_AT = 'deletedAt';

    protected $table = 'client_groups';

    protected $fillable = ['name', 'clientNumber', 'description', 'responsibleUserId', 'salesManagerId'];

    public function members(): HasMany
    {
        return $this->hasMany(ClientGroupMember::class, 'groupId');
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsibleUserId');
    }

    public function salesManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'salesManagerId');
    }
}
