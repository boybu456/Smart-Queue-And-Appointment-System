<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QueueEntry;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Auth;
/**
 * API QueueController
 * ---------------------------------------------------------------
 * JSON version of queue actions.
 * Used by Node.js, mobile apps, and Alpine.js fetch() calls
 * for real-time queue updates without full page reloads.
 *
 * All routes protected by auth:sanctum middleware.
 */
class QueueController extends Controller
{
    /**
     * GET /api/v1/queue
     * ----------------------------------------------------------
     * Returns live queue state for all active services.
     * This is what Alpine.js polls every few seconds.
     */
    public function index(): JsonResponse
    {
        $services = Service::active()
            ->with(['waitingQueue:id,service_id,token,position,status,joined_at'])
            ->get()
            ->map(fn($service) => [
                'id'             => $service->id,
                'name'           => $service->name,
                'duration'       => $service->duration_minutes,
                'queue_count'    => $service->waitingQueue->count(),
                'estimated_wait' => $service->waitingQueue->count() * $service->duration_minutes,
                'is_full'        => $service->waitingQueue->count() >= $service->max_queue_size,
                'queue'          => $service->waitingQueue,
            ]);

        return response()->json(['data' => $services]);
    }

    /**
     * GET /api/v1/queue/{service}
     * ----------------------------------------------------------
     * Live queue for one service + the authenticated user's position.
     */
    public function show(Service $service): JsonResponse
    {
        $waiting = $service->waitingQueue()
            ->with('customer:id,name')
            ->get();

        $myEntry = $waiting->firstWhere('customer_id', Auth::id());

        return response()->json([
            'data' => [
                'service'        => $service->only('id', 'name', 'duration_minutes'),
                'queue'          => $waiting,
                'my_entry'       => $myEntry,
                'my_position'    => $myEntry?->position,
                'estimated_wait' => $myEntry
                    ? ($myEntry->position - 1) * $service->duration_minutes
                    : null,
            ],
        ]);
    }

    /**
     * POST /api/v1/queue/{service}/join
     * ----------------------------------------------------------
     * Join the queue. Returns the new entry with token + position.
     */
    public function join(Service $service): JsonResponse
    {
        $currentCount = $service->waitingQueue()->count();

        if ($currentCount >= $service->max_queue_size) {
            return response()->json(['message' => 'Queue is full.'], 422);
        }

        $alreadyIn = QueueEntry::where('service_id', $service->id)
            ->where('customer_id', Auth::id())
            ->whereIn('status', ['waiting', 'called', 'serving'])
            ->exists();

        if ($alreadyIn) {
            return response()->json(['message' => 'You are already in this queue.'], 422);
        }

        $entry = DB::transaction(function () use ($service, $currentCount) {
            $position = $currentCount + 1;
            $prefix   = strtoupper(substr($service->name, 0, 1));
            $token    = $prefix . str_pad($position, 3, '0', STR_PAD_LEFT);

            $entry = QueueEntry::create([
                'service_id'  => $service->id,
                'customer_id' => Auth::id(),
                'token'       => $token,
                'position'    => $position,
                'status'      => 'waiting',
                'joined_at'   => now(),
            ]);

            Redis::zadd("queue:{$service->id}", now()->timestamp, $entry->id);

            return $entry;
        });

        return response()->json([
            'data'    => $entry,
            'message' => "Joined queue. Your token is {$entry->token}.",
        ], 201);
    }

    /**
     * DELETE /api/v1/queue/{service}/leave
     * ----------------------------------------------------------
     * Leave the queue. Returns 204 on success.
     */
    public function leave(Service $service): JsonResponse
    {
        $entry = QueueEntry::where('service_id', $service->id)
            ->where('customer_id', Auth::id())
            ->where('status', 'waiting')
            ->first();

        if (! $entry) {
            return response()->json(['message' => 'You are not in this queue.'], 404);
        }

        DB::transaction(function () use ($entry, $service) {
            QueueEntry::where('service_id', $service->id)
                ->where('status', 'waiting')
                ->where('position', '>', $entry->position)
                ->decrement('position');

            Redis::zrem("queue:{$service->id}", $entry->id);
            $entry->update(['status' => 'skipped']);
        });

        return response()->json(null, 204);
    }

    /**
     * PUT /api/v1/queue/{service}/advance   [Staff/Admin only]
     * ----------------------------------------------------------
     * Calls the next person. Returns the called entry.
     */
    public function advance(Service $service): JsonResponse
    {
        abort_unless(
            Auth::user()->isStaff() || Auth::user()->isAdmin(),
            403
        );

        $next = $service->waitingQueue()->with('customer:id,name')->first();

        if (! $next) {
            return response()->json(['message' => 'Queue is empty.'], 422);
        }

        DB::transaction(function () use ($next, $service) {
            $next->call();

            QueueEntry::where('service_id', $service->id)
                ->where('status', 'waiting')
                ->decrement('position');

            Redis::publish("queue.{$service->id}.updated", json_encode([
                'event'      => 'next_called',
                'token'      => $next->token,
                'service_id' => $service->id,
            ]));
        });

        return response()->json([
            'data'    => $next->fresh(),
            'message' => "Token {$next->token} called.",
        ]);
    }

    /**
     * PUT /api/v1/queue/entry/{entry}/served   [Staff/Admin only]
     */
    public function markServed(QueueEntry $entry): JsonResponse
    {
        abort_unless(
            Auth::user()->isStaff() || Auth::user()->isAdmin(),
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

        return response()->json([
            'data'    => $entry->fresh(),
            'message' => "Token {$entry->token} served.",
        ]);
    }
}