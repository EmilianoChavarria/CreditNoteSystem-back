<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientGroupMember extends Model
{
    use SoftDeletes;

    public const CREATED_AT = 'createdAt';
    public const UPDATED_AT = 'updatedAt';
    public const DELETED_AT = 'deletedAt';

    protected $table = 'client_group_members';

    protected $fillable = ['groupId', 'clientId'];

    protected $casts = ['clientId' => 'integer'];

    public function group(): BelongsTo
    {
        return $this->belongsTo(ClientGroup::class, 'groupId');
    }
}
