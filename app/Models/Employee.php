<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Employee extends Model
{

    protected $fillable = [
        'name',
        'email',
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
        'ticket_email_sent_at',
    ];

    // Excludes visually-ambiguous characters (0/O, 1/I/L) since this is meant to be typed by hand.
    private const MANUAL_CODE_ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';
    private const MANUAL_CODE_LENGTH   = 8;

    protected static function booted(): void
    {
        static::creating(function (Employee $employee) {
            if (empty($employee->qr_code)) {
                $employee->qr_code = self::generateUniqueQrCode();
            }
            if (empty($employee->manual_code)) {
                $employee->manual_code = self::generateUniqueManualCode();
            }
        });
    }

    private static function generateUniqueQrCode(): string
    {
        do {
            $code = Str::random(32);
        } while (self::where('qr_code', $code)->exists());

        return $code;
    }

    public static function generateUniqueManualCode(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < self::MANUAL_CODE_LENGTH; $i++) {
                $code .= self::MANUAL_CODE_ALPHABET[random_int(0, strlen(self::MANUAL_CODE_ALPHABET) - 1)];
            }
        } while (self::where('manual_code', $code)->exists());

        return $code;
    }

    /** Strip any dashes/spaces and uppercase, so "a3f9-7k2m" matches a stored manual_code. */
    public static function normalizeManualCode(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));
    }

    public function wahanaCheckins()
    {
        return $this->hasMany(WahanaCheckin::class);
    }

    protected $casts = [
        'total_vehicles'       => 'integer',
        'total_passengers'     => 'integer',
        'additional_members'   => 'integer',
        'has_below_two_children' => 'boolean',
        'bus_number'           => 'integer',
        'is_pic_bus'           => 'boolean',
        'switched_from_bus'    => 'boolean',
        'total_bus_passengers' => 'integer',
        'ticket_email_sent_at' => 'datetime',
    ];

    public function scopeSearch($query, ?string $term)
    {
        if (trim($term) === '') return $query;

        return $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower(trim($term)) . '%']);
    }
}
