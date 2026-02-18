<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequestReason extends Model
{
    use HasFactory;

    protected $table = 'requestreasons';
    public $timestamps = false;

    protected $fillable = [
        'name',
    ];

    public function requests()
    {
        return $this->hasMany(Request::class, 'reasonId');
    }
}
