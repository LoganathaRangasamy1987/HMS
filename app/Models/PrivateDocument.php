<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrivateDocument extends Model
{
    protected $fillable = ['hospital_id', 'branch_id', 'user_id', 'name', 'path', 'mime_type', 'size'];

    protected $hidden = ['path'];
}
