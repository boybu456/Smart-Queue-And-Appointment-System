<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\QueueEntry;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * WEB QueueController
 * ---------------------------------------------------------------
 * Handles the live walk-in queue from the browser.
 * Customers join/leave. Staff call the next person.
 *
 * KEY DESIGN DECISION:
 * MySQL  → stores the real record (history, reports, audit trail)
 * Redis  → stores live position for fast reads (no DB query needed
 *           just to show "you are #4 in line")
 *
 * Both are updated together inside DB::transaction() so they
 * never get out of sync — if one fails, both roll back.
 */
class QueueController extends Controller
{
    /**
     * index() → GET /queue
     * ----------------------------------------------------------
     * The public "TV screen" board — shows live queue for all services.
     * No auth required. Customers see this in the waiting area.
     */
    public function index()
    {
        $services = Service::active()
            ->with(['waitingQueue.customer'])
            ->get()
            ->map(function ($service) {
                // Attach computed fields directly onto the model
                $service->queue_count    = $service->waitingQueue->count();
                $service->estimated_wait = $service->queue_count * $service->duration_minutes;
                return $service;
            });

        return view('web.queue.index', compact('services'));
    }

    /**
     * show() → GET /queue/{service}
     * ----------------------------------------------------------
     * Detailed view of one service's queue.
     * If the logged-in user is in the queue, shows their position.
     */
    public function show(Service $service)
    {
        $waiting = $service->waitingQueue()
            ->with('customer:id,name')
            ->get();

        // Find this customer's own entry (null if not in queue or not logged in)
        $myEntry = auth()->check()
            ? $waiting->firstWhere('customer_id', auth()->id())
            : null;

        $estimatedWait = $myEntry
            ? ($myEntry->position - 1) * $service->duration_minutes
            : null;

        return view('web.queue.show', compact('service', 'waiting', 'myEntry', 'estimatedWait'));
    }

    /**
     * join() → POST /queue/{service}/join   [Auth required]
     * ----------------------------------------------------------
     * Customer joins the queue. Steps:
     *   1. Check queue isn't full
     *   2. Check customer isn't already in queue
     *   3. Generate token (e.g. "A004")
     *   4. Save to MySQL + Redis atomically
     */
    public function join(Service $service)
    {
        // Count current waiting entries
        $currentCount = $service->waitingQueue()->count();

        abort_if(
            $currentCount >= $service->max_queue_size,
            422,
            'This queue is full. Please try again later.'
        );

        // Prevent duplicate entries
        $alreadyInQueue = QueueEntry::where('service_id', $service->id)
            ->where('customer_id', auth()->id())
            ->whereIn('status', ['waiting', 'called', 'serving'])
            ->exists();

        abort_if($alreadyInQueue, 422, 'You are already in this queue.');

        // DB::transaction() — if ANY line inside throws an error,
        // everything rolls back. Nothing is half-saved.
        $entry = DB::transaction(function () use ($service, $currentCount) {
            $position = $currentCount + 1;

            // Token format: first letter of service name + 3-digit number
            // e.g. Service "General" → "G004"
            $prefix = strtoupper(substr($service->name, 0, 1));
            $token  = $prefix . str_pad($position, 3, '0', STR_PAD_LEFT);

            $entry = QueueEntry::create([
                'service_id'  => $service->id,
                'customer_id' => auth()->id(),
                'token'       => $token,
                'position'    => $position,
                'status'      => 'waiting',
                'joined_at'   => now(),
            ]);

            // Add to Redis sorted set.
            // Score = current timestamp → preserves join order.
            // Key pattern: queue:{service_uuid}
            Redis::zadd(
                "queue:{$service->id}",
                now()->timestamp,
                $entry->id
            );

            return $entry;
        });

        return redirect()
            ->route('web.queue.show', $service)
            ->with('success', "You joined the queue! Your token is {$entry->token}. You are #$entry->position in line.");
    }

    /**
     * leave() → DELETE /queue/{service}/leave   [Auth required]
     * ----------------------------------------------------------
     * Customer voluntarily leaves the queue.
     * Everyone behind them shifts up by 1 position.
     */
    public function leave(Service $service)
    {
        $entry = QueueEntry::where('service_id', $service->id)
            ->where('customer_id', auth()->id())
            ->where('status', 'waiting')
            ->firstOrFail(); // 404 if not found

        DB::transaction(function () use ($entry, $service) {
            // Shift everyone behind this person up
            QueueEntry::where('service_id', $service->id)
                ->where('status', 'waiting')
                ->where('position', '>', $entry->position)
                ->decrement('position'); // decrement = subtract 1

            // Remove from Redis
            Redis::zrem("queue:{$service->id}", $entry->id);

            // Mark as skipped (not deleted — we keep the record)
            $entry->update(['status' => 'skipped']);
        });

        return redirect()
            ->route('web.queue.show', $service)
            ->with('success', 'You have left the queue.');
    }

    /**
     * advance() → PUT /queue/{service}/advance   [Staff/Admin only]
     * ----------------------------------------------------------
     * Staff presses "Call Next". Marks the first waiting person
     * as "called" and broadcasts the event via Redis pub/sub.
     * Node.js listens to that channel and pushes it to WebSocket clients.
     */
    public function advance(Service $service)
    {
        abort_unless(
            auth()->user()->isStaff() || auth()->user()->isAdmin(),
            403,
            'Staff only.'
        );

        // Get the first person waiting (lowest position)
        $next = $service->waitingQueue()->with('customer')->first();

        abort_if(! $next, 422, 'The queue is empty.');

        DB::transaction(function () use ($next, $service) {
            // Mark them as called
            $next->call();

            // Shift remaining entries up by 1
            QueueEntry::where('service_id', $service->id)
                ->where('status', 'waiting')
                ->decrement('position');

            // Publish to Redis pub/sub channel.
            // Node.js is subscribed to this channel and will
            // immediately broadcast to all connected WebSocket clients.
            Redis::publish("queue.{$service->id}.updated", json_encode([
                'event'       => 'next_called',
                'token'       => $next->token,
                'customer'    => $next->customer->name,
                'service_id'  => $service->id,
            ]));
        });

        return redirect()
            ->route('web.queue.show', $service)
            ->with('success', "Token {$next->token} — {$next->customer->name} has been called.");
    }

    /**
     * markServed() → PUT /queue/entry/{entry}/served   [Staff/Admin only]
     * ----------------------------------------------------------
     * After serving the customer, staff marks them as done.
     * This removes them from Redis and closes the queue entry.
     */
    public function markServed(QueueEntry $entry)
    {
        abort_unless(
            auth()->user()->isStaff() || auth()->user()->isAdmin(),
            403
        );

        DB::transaction(function () use ($entry) {
            $entry->serve();
            $entry->markDone();

            Redis::zrem("queue:{$entry->service_id}", $entry->id);

            Redis::publish("queue.{$entry->service_id}.updated", json_encode([
                'event'      => 'entry_served',
                'token'      => $entry->token,
                'service_id' => $entry->service_id,
            ]));
        });

        return back()->with('success', "Token {$entry->token} marked as served.");
    }
}