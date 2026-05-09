<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

/**
 * WEB AppointmentController
 * ---------------------------------------------------------------
 * Handles appointment booking, viewing, rescheduling, cancelling.
 *
 * IMPORTANT CONCEPTS USED HERE:
 *
 * 1. PESSIMISTIC LOCKING (lockForUpdate)
 *    When two people book the same slot at the same time,
 *    lockForUpdate() makes the second request wait until the
 *    first transaction finishes. Prevents double-booking.
 *
 * 2. CARBON
 *    Laravel's date library. Carbon::parse('2025-01-15 09:00')
 *    gives you a powerful date object with methods like
 *    ->addMinutes(30), ->format('h:i A'), ->isFuture() etc.
 *
 * 3. STATE MACHINE
 *    Appointments move through statuses in one direction:
 *    pending → confirmed → done
 *                       → no_show
 *    pending → cancelled
 *    You can't un-cancel or un-complete an appointment.
 */
class AppointmentController extends Controller
{
    /**
     * index() → GET /appointments
     * ----------------------------------------------------------
     * Customers see their own appointments.
     * Staff and admins see all appointments with filters.
     */
    public function index(Request $request)
    {
        $user  = Auth::user();
        $query = Appointment::with(['customer:id,name,phone', 'staff:id,name', 'service:id,name']);

        if ($user->isCustomer()) {
            // Customers only see their own
            $query->where('customer_id', $user->id);
        } else {
            // Staff/Admin can filter by date or status
            if ($request->filled('date')) {
                $query->onDate($request->date);
            }
            if ($request->filled('status')) {
                $query->status($request->status);
            }
        }

        $appointments = $query->latest('scheduled_at')->paginate(15);

        // Pass status list for the filter dropdown
        $statuses = Appointment::STATUSES;

        return view('web.appointments.index', compact('appointments', 'statuses'));
    }

    /**
     * create() → GET /appointments/create
     * ----------------------------------------------------------
     * Shows the booking form.
     * Loads services + staff for the dropdowns.
     */
    public function create()
    {
        $services = Service::active()->get(['id', 'name', 'duration_minutes']);
        $staff    = User::where('role', 'staff')->get(['id', 'name']);

        return view('web.appointments.create', compact('services', 'staff'));
    }

    /**
     * store() → POST /appointments
     * ----------------------------------------------------------
     * Saves a new appointment.
     *
     * DOUBLE-BOOKING PREVENTION LOGIC:
     * Given a new slot from 09:00–09:30, a conflict exists if:
     *   any existing appointment for the same staff starts before 09:30
     *   AND ends after 09:00
     * This catches all overlap cases (partial start, partial end, full overlap).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'service_id'   => 'required|uuid|exists:services,id',
            'staff_id'     => 'required|uuid|exists:users,id',
            'scheduled_at' => 'required|date|after:now',
            'notes'        => 'nullable|string|max:500',
        ]);

        $service    = Service::findOrFail($validated['service_id']);
        $newStart   = Carbon::parse($validated['scheduled_at']);
        $newEnd     = $newStart->copy()->addMinutes($service->duration_minutes);

        // Wrap in transaction — rolls back if conflict found
        DB::transaction(function () use ($validated, $newStart, $newEnd) {
            // lockForUpdate() locks these rows until transaction ends.
            // Any other concurrent request trying to book the same staff
            // will wait here until this transaction commits or rolls back.
            $conflict = Appointment::lockForUpdate()
                ->where('staff_id', $validated['staff_id'])
                ->whereNotIn('status', ['cancelled', 'done', 'no_show'])
                ->where(function ($query) use ($newStart, $newEnd) {
                    $query->whereBetween('scheduled_at', [$newStart, $newEnd->subSecond()])
                          ->orWhereRaw('DATE_ADD(scheduled_at, INTERVAL (
                              SELECT duration_minutes FROM services
                              WHERE id = appointments.service_id
                           ) MINUTE) > ?', [$newStart]);
                })
                ->exists();

            abort_if($conflict, 422, 'That time slot is already taken. Please choose another.');

            Appointment::create([
                ...$validated,
                'customer_id' => Auth::id(),
                'status'      => 'pending',
            ]);
        });

        return redirect()
            ->route('web.appointments.index')
            ->with('success', 'Appointment booked! You will receive a confirmation shortly.');
    }

    /**
     * show() → GET /appointments/{appointment}
     * ----------------------------------------------------------
     * Shows full detail of one appointment.
     * Route Model Binding auto-fetches the Appointment by ID.
     * Gate: customers can only view their own.
     */
    public function show(Appointment $appointment)
    {
        abort_if(
            Auth::user()->isCustomer() && $appointment->customer_id !== Auth::id(),
            403,
            'You do not have access to this appointment.'
        );

        $appointment->load(['customer', 'staff', 'service']);

        return view('web.appointments.show', compact('appointment'));
    }

    /**
     * edit() → GET /appointments/{appointment}/edit
     * ----------------------------------------------------------
     * Shows the reschedule form. Only for pending/confirmed appointments.
     */
    public function edit(Appointment $appointment)
    {
        abort_if(
            $appointment->isDone() || $appointment->isCancelled(),
            403,
            'This appointment can no longer be edited.'
        );

        abort_if(
            Auth::user()->isCustomer() && $appointment->customer_id !== Auth::id(),
            403
        );

        $services = Service::active()->get(['id', 'name', 'duration_minutes']);
        $staff    = User::where('role', 'staff')->get(['id', 'name']);

        return view('web.appointments.edit', compact('appointment', 'services', 'staff'));
    }

    /**
     * update() → PUT /appointments/{appointment}
     * ----------------------------------------------------------
     * Saves rescheduled appointment data.
     * Only scheduled_at and notes can be changed after booking.
     */
    public function update(Request $request, Appointment $appointment)
    {
        abort_if($appointment->isDone() || $appointment->isCancelled(), 403);

        $validated = $request->validate([
            'scheduled_at' => 'required|date|after:now',
            'notes'        => 'nullable|string|max:500',
        ]);

        $appointment->update($validated);

        return redirect()
            ->route('web.appointments.show', $appointment)
            ->with('success', 'Appointment rescheduled successfully.');
    }

    /**
     * destroy() → DELETE /appointments/{appointment}
     * ----------------------------------------------------------
     * Cancels the appointment. We never hard-delete appointment records
     * — they're needed for reports, no-show tracking, and audit trails.
     */
    public function destroy(Appointment $appointment)
    {
        abort_if($appointment->isDone(), 403, 'Cannot cancel a completed appointment.');

        abort_if(
            auth()->Auth::isCustomer() && $appointment->customer_id !== Auth::id(),
            403
        );

        $appointment->cancel();

        return redirect()
            ->route('web.appointments.index')
            ->with('success', 'Appointment cancelled.');
    }
}