<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Appointment;

class Service extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'description',
        'duration_minutes',
        'max_queue_size',
        'is_active',
    ];

    protected $casts = [
        'is_active'        => 'boolean',
        'duration_minutes' => 'integer',
        'max_queue_size'   => 'integer',
    ];

    // ─── Scopes ───────────────────────────────────────────────

    /**
     * Only return services that are currently active.
     * Usage: Service::active()->get()
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ─── Relationships ────────────────────────────────────────

    /**
     * All appointments booked for this service.
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * All live queue entries for this service.
     */
    public function queueEntries(): HasMany
    {
        return $this->hasMany(QueueEntry::class);
    }

    /**
     * Currently waiting entries only.
     * Usage: $service->waitingQueue()->count()
     */
    public function waitingQueue(): HasMany
    {
        return $this->hasMany(QueueEntry::class)
                    ->where('status', 'waiting')
                    ->orderBy('position');
    }

    /**
     * Staff availability rules defined for this service.
     */
    public function staffAvailability(): HasMany
    {
        return $this->hasMany(StaffAvailability::class);
    }
}