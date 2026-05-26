<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailConfig extends Model
{
    protected $table = 'emailconfig';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'emailSupport',
        'emailMode',
        'overrideEmail',
        'createdAt',
        'updatedAt',
    ];
}
