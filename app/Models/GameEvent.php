<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameEvent extends Model
{
    protected $table = 'events';

    protected $guarded = [];

    public $timestamps = false;

    protected $casts = ['result' => 'array'];
}
