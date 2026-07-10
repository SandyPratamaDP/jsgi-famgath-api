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
        'additional_vehicles',
        'has_below_two_children',
        'has_below_one_year_child',
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

    // Fields that show up on the printed/emailed ticket — if either a re-import or a
    // manual admin edit changes any of these, the cached PDF/PNG (and, if already sent,
    // the emailed copy) is stale and needs to be regenerated/resent.
    public const TICKET_RELEVANT_FIELDS = [
        'name', 'email', 'transport_type', 'bus_number', 'is_pic_bus',
        'total_bus_passengers', 'total_passengers', 'total_vehicles',
        'pickup_point', 'has_below_two_children', 'additional_members',
        'additional_vehicles',
    ];

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

    /**
     * The gate (Ancol) waives tickets under 2 years old, so import already excludes
     * those children from total_passengers. The rides (wahana) only waive tickets
     * under 1 year old, so a child aged 1-2 was excluded at the gate but still needs
     * a wahana ticket — add them back, but only for the wahana headcount, never
     * persisted to total_passengers itself.
     */
    public function needsWahanaHeadcountBonus(): bool
    {
        return $this->has_below_two_children && !$this->has_below_one_year_child;
    }

    protected $casts = [
        'total_vehicles'       => 'integer',
        'total_passengers'     => 'integer',
        'additional_members'   => 'integer',
        'additional_vehicles'  => 'integer',
        'has_below_two_children' => 'boolean',
        'has_below_one_year_child' => 'boolean',
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

    /** Only these employees ever get an individual ticket file — regular bus riders
     *  share their PIC's manifest ticket and have no pdf_filename of their own. */
    public function scopeTicketEligible($query)
    {
        return $query->where(function ($q) {
            $q->whereIn('transport_type', ['private_car', 'operational'])
              ->orWhere('is_pic_bus', true);
        });
    }

    public function isTicketEligible(): bool
    {
        return $this->transport_type === 'private_car'
            || $this->transport_type === 'operational'
            || $this->is_pic_bus;
    }
}
