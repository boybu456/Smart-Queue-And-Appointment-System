<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * WEB ServiceController
 * ---------------------------------------------------------------
 * Handles HTTP requests that come from the BROWSER.
 * Every method returns a Blade VIEW, not JSON.
 *
 * HOW LARAVEL PROCESSES A REQUEST (important to understand):
 *   Browser → routes/web.php → this Controller → Model → View → Browser
 *
 * RESOURCEFUL CONTROLLER CONVENTION:
 *   index()   → list all
 *   create()  → show the "add new" form
 *   store()   → save the new record (form submission)
 *   show()    → view one record
 *   edit()    → show the "edit" form
 *   update()  → save the edited record
 *   destroy() → delete/deactivate
 */
class ServiceController extends Controller
{
    /**
     * index() → GET /services
     * ----------------------------------------------------------
     * Shows a paginated list of all active services.
     * withCount() adds queue size + appointment count without
     * loading every related record into memory.
     */
    public function index()
    {
        $services = Service::withCount([
            'appointments',
            // Alias: only count waiting queue entries
            'queueEntries as waiting_count' => fn($q) => $q->where('status', 'waiting'),
        ])
        ->latest()
        ->paginate(10);

        // compact('services') is shorthand for ['services' => $services]
        // It passes the variable to the view so Blade can use {{ $services }}
        return view('web.services.index', compact('services'));
    }

    /**
     * create() → GET /services/create   [Admin only]
     * ----------------------------------------------------------
     * Just shows a blank form. No data needed from the DB.
     */
    public function create()
    {
        $this->authorizeAdmin();
        return view('web.services.create');
    }

    /**
     * store() → POST /services   [Admin only]
     * ----------------------------------------------------------
     * Receives the form submission, validates it, saves to DB.
     *
     * VALIDATION RULES EXPLAINED:
     *   required          → field cannot be empty
     *   string|max:100    → must be text, max 100 characters
     *   unique:services,name → no other row in services table has this name
     *   integer|min:5     → must be a whole number, at least 5
     */
    public function store(Request $request)
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'name'             => 'required|string|max:100|unique:services,name',
            'description'      => 'nullable|string|max:500',
            'duration_minutes' => 'required|integer|min:5|max:480',
            'max_queue_size'   => 'required|integer|min:1|max:500',
        ]);

        Service::create([
            ...$validated,       // spread operator — same as listing each key
            'is_active' => true, // always active when first created
        ]);

        // redirect()->route() sends the browser to a named route.
        // ->with('success', ...) flashes a one-time message to the session.
        // Your layout reads session('success') and shows a green alert.
        return redirect()
            ->route('web.services.index')
            ->with('success', 'Service created successfully.');
    }

    /**
     * show() → GET /services/{service}
     * ----------------------------------------------------------
     * Shows one service with its current queue and today's appointments.
     * Laravel automatically fetches the Service by ID from the URL
     * — this is called Route Model Binding. No findOrFail() needed.
     */
    public function show(Service $service)
    {
        // load() fetches relationships AFTER the model is already retrieved.
        // Only load what the view actually needs.
        $service->load([
            'waitingQueue.customer',
            'staffAvailability.staff',
        ]);

        $todayAppointments = $service->appointments()
            ->with(['customer', 'staff'])
            ->today()
            ->whereNotIn('status', ['cancelled', 'done'])
            ->orderBy('scheduled_at')
            ->get();

        return view('web.services.show', compact('service', 'todayAppointments'));
    }

    /**
     * edit() → GET /services/{service}/edit   [Admin only]
     * ----------------------------------------------------------
     * Shows the edit form pre-filled with existing data.
     * Blade uses old() or the model value to populate inputs.
     */
    public function edit(Service $service)
    {
        $this->authorizeAdmin();
        return view('web.services.edit', compact('service'));
    }

    /**
     * update() → PUT /services/{service}   [Admin only]
     * ----------------------------------------------------------
     * Validates and saves changes to an existing service.
     * Note the unique rule — we exclude the current service's own ID
     * so it doesn't flag its own name as a duplicate.
     */
    public function update(Request $request, Service $service)
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'name'             => "required|string|max:100|unique:services,name,{$service->id}",
            'description'      => 'nullable|string|max:500',
            'duration_minutes' => 'required|integer|min:5|max:480',
            'max_queue_size'   => 'required|integer|min:1|max:500',
            'is_active'        => 'boolean',
        ]);

        $service->update($validated);

        return redirect()
            ->route('web.services.show', $service)
            ->with('success', 'Service updated.');
    }

    /**
     * destroy() → DELETE /services/{service}   [Admin only]
     * ----------------------------------------------------------
     * We DEACTIVATE rather than hard delete — preserves all
     * historical appointment and queue data tied to this service.
     */
    public function destroy(Service $service)
    {
        $this->authorizeAdmin();

        $service->update(['is_active' => false]);

        return redirect()
            ->route('web.services.index')
            ->with('success', "{$service->name} has been deactivated.");
    }

    /**
     * Private helper — aborts with 403 if user is not admin.
     * Reusable inside this controller so we don't repeat the check.
     */
    private function authorizeAdmin(): void
    {
        abort_unless(Auth::user()->isAdmin(), 403, 'Admins only.');
    }
}