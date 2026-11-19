<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SibBmBuildingCache extends Model
{
    protected $table = 'sib_bm_buildings_cache';

    protected $fillable = [
        'external_id',
        'name',
        'category',
        'function_desc',
        'description',
        'latitude',
        'longitude',
        'land_area',
        'building_area',
        'capacity',
        'facilities',
        'photo_url',
        'detail_url',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'land_area' => 'float',
            'building_area' => 'float',
            'capacity' => 'integer',
            'facilities' => 'array',
            'synced_at' => 'datetime',
        ];
    }
}
