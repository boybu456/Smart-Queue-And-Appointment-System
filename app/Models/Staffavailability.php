<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Service;
use App\Models\User;

class StaffAvailability extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'staff_id',
        'service_id',
        'day_of_week',
        'start_time',
        'end_time',
        'is_active',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'day_of_week' => 'integer',
    ];

    // Human-readable day names — index matches day_of_week value
    const DAYS = [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ];

    // ─── Helpers ─────────────────────────────────────────────

    /**
     * Get the human-readable day name.
     * Usage: $availability->dayName() → "Monday"
     */
    public function dayName(): string
    {
        return self::DAYS[$this->day_of_week] ?? 'Unknown';
    }

    /**
     * How many minutes long is this availability window?
     */
    public function durationMinutes(): int
    {
        [$startH, $startM] = explode(':', $this->start_time);
        [$endH,   $endM]   = explode(':', $this->end_time);

        return (($endH * 60) + $endM) - (($startH * 60) + $startM);
    }

    // ─── Scopes ───────────────────────────────────────────────

    /**
     * Only active availability rules.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Filter by a specific day of week.
     * Usage: StaffAvailability::forDay(1)->get() — Mondays
     */
    public function scopeForDay($query, int $day)
    {
        return $query->where('day_of_week', $day);
    }

    // ─── Relationships ────────────────────────────────────────

    /**
     * The staff member this availability belongs to.
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    /**
     * The service this availability applies to.
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}