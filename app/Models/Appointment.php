<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'customer_id',
        'staff_id',
        'service_id',
        'scheduled_at',
        'status',
        'notes',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
    ];

    // Valid status transitions — used in state machine checks
    const STATUSES = ['pending', 'confirmed', 'no_show', 'done', 'cancelled'];

    // ─── Status helpers ───────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isDone(): bool
    {
        return $this->status === 'done';
    }

    public function confirm(): bool
    {
        return $this->update(['status' => 'confirmed']);
    }

    public function cancel(): bool
    {
        return $this->update(['status' => 'cancelled']);
    }

    public function markDone(): bool
    {
        return $this->update(['status' => 'done']);
    }

    // ─── Scopes ───────────────────────────────────────────────

    /**
     * Filter by a specific status.
     * Usage: Appointment::status('confirmed')->get()
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Appointments scheduled for today.
     */
    public function scopeToday($query)
    {
        return $query->whereDate('scheduled_at', today());
    }

    /**
     * Appointments scheduled for a specific date.
     */
    public function scopeOnDate($query, string $date)
    {
        return $query->whereDate('scheduled_at', $date);
    }

    /**
     * Upcoming (future + not cancelled/done).
     */
    public function scopeUpcoming($query)
    {
        return $query->where('scheduled_at', '>', now())
                     ->whereNotIn('status', ['cancelled', 'done']);
    }

    // ─── Relationships ────────────────────────────────────────

    /**
     * The customer who booked this appointment.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * The staff member handling this appointment.
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    /**
     * The service being performed.
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}