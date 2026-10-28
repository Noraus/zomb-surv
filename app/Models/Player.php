<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Player extends Model
{
    protected $table = 'players';

    protected $guarded = [];

    public $timestamps = false;

    protected $casts = ['lat' => 'float', 'lon' => 'float', 'capacity' => 'float', 'energy' => 'integer', 'health' => 'integer', 'hunger' => 'integer', 'thirst' => 'integer'];
}
