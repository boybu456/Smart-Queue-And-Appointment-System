<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
/**
 * API ServiceController
 * ---------------------------------------------------------------
 * Same logic as the Web controller — but returns JSON instead of views.
 * Used by your Node.js server, mobile apps, or any JS frontend.
 *
 * PROTECTED BY: auth:sanctum middleware (set in routes/api.php)
 * This means every request needs an Authorization header:
 *   Authorization: Bearer 1|abc123yourtokenhere
 *
 * RESPONSE FORMAT — always consistent:
 *   Success: { "data": {...}, "message": "..." }
 *   Error:   { "message": "...", "errors": {...} }
 */
class ServiceController extends Controller
{
    /**
     * GET /api/v1/services
     * ----------------------------------------------------------
     * Returns all active services as JSON.
     * The 'active' scope is defined in the Service model.
     */
    public function index(): JsonResponse
    {
        $services = Service::active()
            ->withCount([
                'appointments',
                'queueEntries as waiting_count' => fn($q) => $q->where('status', 'waiting'),
            ])
            ->latest()
            ->get();

        // response()->json() sets Content-Type: application/json automatically
        // HTTP 200 = OK (default, can omit)
        return response()->json([
            'data' => $services,
        ]);
    }

    /**
     * POST /api/v1/services   [Admin only]
     * ----------------------------------------------------------
     * Creates a new service. Expects JSON body:
     * {
     *   "name": "Consultation",
     *   "duration_minutes": 30,
     *   "max_queue_size": 50,
     *   "description": "Optional"
     * }
     */
    public function store(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->isAdmin(), 403, 'Admins only.');

        $validated = $request->validate([
            'name'             => 'required|string|max:100|unique:services,name',
            'description'      => 'nullable|string|max:500',
            'duration_minutes' => 'required|integer|min:5|max:480',
            'max_queue_size'   => 'required|integer|min:1|max:500',
        ]);

        $service = Service::create([
            ...$validated,
            'is_active' => true,
        ]);

        // HTTP 201 = Created (use this instead of 200 when creating a resource)
        return response()->json([
            'data'    => $service,
            'message' => 'Service created successfully.',
        ], 201);
    }

    /**
     * GET /api/v1/services/{service}
     * ----------------------------------------------------------
     * Returns a single service with queue and availability data.
     */
    public function show(Service $service): JsonResponse
    {
        $service->load([
            'waitingQueue.customer:id,name',  // only load id+name, not password etc.
            'staffAvailability.staff:id,name',
        ]);

        // Append computed stats
        $service->waiting_count   = $service->waitingQueue->count();
        $service->estimated_wait  = $service->waiting_count * $service->duration_minutes;

        return response()->json([
            'data' => $service,
        ]);
    }

    /**
     * PUT /api/v1/services/{service}   [Admin only]
     */
    public function update(Request $request, Service $service): JsonResponse
    {
        abort_unless(Auth::user()->isAdmin(), 403, 'Admins only.');

        $validated = $request->validate([
            'name'             => "required|string|max:100|unique:services,name,{$service->id}",
            'description'      => 'nullable|string|max:500',
            'duration_minutes' => 'required|integer|min:5|max:480',
            'max_queue_size'   => 'required|integer|min:1|max:500',
            'is_active'        => 'boolean',
        ]);

        $service->update($validated);

        return response()->json([
            'data'    => $service->fresh(), // fresh() re-fetches from DB after update
            'message' => 'Service updated.',
        ]);
    }

    /**
     * DELETE /api/v1/services/{service}   [Admin only]
     * ----------------------------------------------------------
     * Deactivates the service. Returns 204 No Content (standard for DELETE).
     */
    public function destroy(Service $service): JsonResponse
    {
        abort_unless(Auth::user()->isAdmin(), 403, 'Admins only.');

        $service->update(['is_active' => false]);

        // HTTP 204 = No Content. Success but nothing to return.
        return response()->json(null, 204);
    }
}