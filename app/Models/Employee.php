<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{

    protected $fillable = [
        'name',
        'employee_type',
        'total_vehicles',
        'total_passengers',
        'additional_members',
        'has_below_two_children',
        'transport_type',
        'switched_from_bus',
        'bus_number',
        'is_pic_bus',
        'total_bus_passengers',
        'pickup_point',
        'pdf_filename',
    ];

    protected $casts = [
        'total_vehicles'       => 'integer',
        'total_passengers'     => 'integer',
        'additional_members'   => 'integer',
        'has_below_two_children' => 'boolean',
        'bus_number'           => 'integer',
        'is_pic_bus'           => 'boolean',
        'switched_from_bus'    => 'boolean',
        'total_bus_passengers' => 'integer',
    ];

    public function scopeSearch($query, ?string $term)
    {
        if (trim($term) === '') return $query;

        return $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower(trim($term)) . '%']);
    }
}
