<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorldLocation extends Model
{
    protected $table = 'world_locations';

    protected $guarded = [];

    public $timestamps = false;

    protected $casts = ['generation_context' => 'array', 'tags' => 'array', 'geometry' => 'array', 'lat' => 'float', 'lon' => 'float', 'risk' => 'integer', 'visits' => 'integer'];
}
