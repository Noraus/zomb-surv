<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResourceDefinition extends Model
{
    protected $table = 'resource_definitions';

    protected $guarded = [];

    public $timestamps = false;

    protected $casts = ['properties' => 'array', 'weight' => 'float'];

    public $incrementing = false;

    protected $keyType = 'string';
}
