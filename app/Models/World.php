<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class World extends Model
{
    protected $table = 'worlds';

    protected $guarded = [];

    public $timestamps = false;

    protected $casts = [];
}
