<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QueueEntry extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'service_id',
        'customer_id',
        'token',
        'position',
        'status',
        'joined_at',
        'served_at',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'served_at' => 'datetime',
        'position'  => 'integer',
    ];

    // ─── Status helpers ───────────────────────────────────────

    public function isWaiting(): bool
    {
        return $this->status === 'waiting';
    }

    public function call(): bool
    {
        return $this->update(['status' => 'called']);
    }

    public function serve(): bool
    {
        return $this->update([
            'status'    => 'serving',
            'served_at' => now(),
        ]);
    }

    public function markDone(): bool
    {
        return $this->update(['status' => 'done']);
    }

    public function skip(): bool
    {
        return $this->update(['status' => 'skipped']);
    }

    /**
     * Calculate how long the customer waited (in minutes).
     * Returns null if not yet served.
     */
    public function waitMinutes(): ?int
    {
        if (! $this->served_at) {
            return null;
        }

        return (int) $this->joined_at->diffInMinutes($this->served_at);
    }

    // ─── Scopes ───────────────────────────────────────────────

    /**
     * Only waiting entries, ordered by position.
     * Usage: QueueEntry::waiting()->get()
     */
    public function scopeWaiting($query)
    {
        return $query->where('status', 'waiting')->orderBy('position');
    }

    /**
     * Active entries (waiting + called + serving).
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['waiting', 'called', 'serving'])
                     ->orderBy('position');
    }

    // ─── Relationships ────────────────────────────────────────

    /**
     * The service this queue entry belongs to.
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * The customer in this queue position.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }
}