<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'nik',
        'name',
        'department',
        'employee_type',
        'total_vehicles',
        'total_passengers',
        'transport_type',
        'bus_number',
        'is_pic_bus',
        'total_bus_passengers',
        'pickup_point',
        'attendance_status',
        'scanned_at',
    ];

    protected $casts = [
        'total_vehicles' => 'integer',
        'total_passengers' => 'integer',
        'bus_number' => 'integer',
        'is_pic_bus' => 'boolean',
        'total_bus_passengers' => 'integer',
        'scanned_at' => 'datetime',
    ];

    public function scopeSearch($query, ?string $term)
    {
        if (trim($term) === '') {
            return $query;
        }

        $term = strtolower(trim($term));

        return $query->whereRaw('LOWER(name) LIKE ?', ["%{$term}%"])
            ->orWhereRaw('LOWER(nik) LIKE ?', ["%{$term}%"]);
    }
}
