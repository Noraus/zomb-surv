<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OsmImport extends Model
{
    protected $table = 'osm_imports';

    protected $guarded = [];

    public $timestamps = true;

    protected $casts = ['bbox' => 'array'];
}
