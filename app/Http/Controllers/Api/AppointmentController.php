<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

/**
 * API AppointmentController
 * ---------------------------------------------------------------
 * JSON version of appointment management.
 * Same business logic as the Web controller,
 * but returns structured JSON instead of Blade views.
 */
class AppointmentController extends Controller
{
    /**
     * GET /api/v1/appointments
     * ----------------------------------------------------------
     * Optional query params:
     *   ?date=2025-01-15
     *   ?status=confirmed
     *   ?service_id=uuid
     */
    public function index(Request $request): JsonResponse
    {
        $user  = Auth::user();
        $query = Appointment::with(['customer:id,name,phone', 'staff:id,name', 'service:id,name,duration_minutes']);

        if ($user->isCustomer()) {
            $query->where('customer_id', $user->id);
        } else {
            if ($request->filled('date'))       $query->onDate($request->date);
            if ($request->filled('status'))     $query->status($request->status);
            if ($request->filled('service_id')) $query->where('service_id', $request->service_id);
        }

        $appointments = $query->latest('scheduled_at')->paginate(15);

        return response()->json([
            'data' => $appointments,
        ]);
    }

    /**
     * POST /api/v1/appointments
     * ----------------------------------------------------------
     * Book a new appointment. Expects JSON body:
     * {
     *   "service_id": "uuid",
     *   "staff_id": "uuid",
     *   "scheduled_at": "2025-01-15 09:00:00",
     *   "notes": "optional"
     * }
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_id'   => 'required|uuid|exists:services,id',
            'staff_id'     => 'required|uuid|exists:users,id',
            'scheduled_at' => 'required|date|after:now',
            'notes'        => 'nullable|string|max:500',
        ]);

        $service  = Service::findOrFail($validated['service_id']);
        $newStart = Carbon::parse($validated['scheduled_at']);
        $newEnd   = $newStart->copy()->addMinutes($service->duration_minutes);

        $appointment = DB::transaction(function () use ($validated, $newStart, $newEnd) {
            $conflict = Appointment::lockForUpdate()
                ->where('staff_id', $validated['staff_id'])
                ->whereNotIn('status', ['cancelled', 'done', 'no_show'])
                ->where(function ($q) use ($newStart, $newEnd) {
                    $q->whereBetween('scheduled_at', [$newStart, $newEnd->subSecond()])
                      ->orWhereRaw('DATE_ADD(scheduled_at, INTERVAL (
                          SELECT duration_minutes FROM services
                          WHERE id = appointments.service_id
                       ) MINUTE) > ?', [$newStart]);
                })
                ->exists();

            if ($conflict) {
                // Throw an exception to trigger rollback
                throw new \Exception('CONFLICT');
            }

            return Appointment::create([
                ...$validated,
                'customer_id' => Auth::id(),
                'status'      => 'pending',
            ]);
        });

        if (! $appointment) {
            return response()->json(['message' => 'That time slot is already taken.'], 422);
        }

        return response()->json([
            'data'    => $appointment->load(['customer', 'staff', 'service']),
            'message' => 'Appointment booked successfully.',
        ], 201);
    }

    /**
     * GET /api/v1/appointments/{appointment}
     */
    public function show(Appointment $appointment): JsonResponse
    {
        abort_if(
            Auth::user()->isCustomer() && $appointment->customer_id !== Auth::id(),
            403
        );

        return response()->json([
            'data' => $appointment->load(['customer', 'staff', 'service']),
        ]);
    }

    /**
     * PUT /api/v1/appointments/{appointment}
     * ----------------------------------------------------------
     * Reschedule an appointment.
     */
    public function update(Request $request, Appointment $appointment): JsonResponse
    {
        abort_if($appointment->isDone() || $appointment->isCancelled(), 403);
        abort_if(
            Auth::user()->isCustomer() && $appointment->customer_id !== Auth::id(),
            403
        );

        $validated = $request->validate([
            'scheduled_at' => 'required|date|after:now',
            'notes'        => 'nullable|string|max:500',
        ]);

        $appointment->update($validated);

        return response()->json([
            'data'    => $appointment->fresh()->load(['customer', 'staff', 'service']),
            'message' => 'Appointment rescheduled.',
        ]);
    }

    /**
     * DELETE /api/v1/appointments/{appointment}
     * ----------------------------------------------------------
     * Cancel an appointment. Returns 204 on success.
     */
    public function destroy(Appointment $appointment): JsonResponse
    {
        abort_if($appointment->isDone(), 403, 'Cannot cancel a completed appointment.');
        abort_if(
            Auth::user()->isCustomer() && $appointment->customer_id !== Auth::id(),
            403
        );

        $appointment->cancel();

        return response()->json(null, 204);
    }
}