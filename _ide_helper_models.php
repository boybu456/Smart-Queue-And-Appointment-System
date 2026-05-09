<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * @property string $id
 * @property string $customer_id
 * @property string $staff_id
 * @property string $service_id
 * @property \Illuminate\Support\Carbon $scheduled_at
 * @property string $status
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $customer
 * @property-read \App\Models\Service $service
 * @property-read \App\Models\User $staff
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Appointment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Appointment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Appointment onDate(string $date)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Appointment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Appointment status(string $status)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Appointment today()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Appointment upcoming()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Appointment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Appointment whereCustomerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Appointment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Appointment whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Appointment whereScheduledAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Appointment whereServiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Appointment whereStaffId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Appointment whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Appointment whereUpdatedAt($value)
 */
	class Appointment extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $service_id
 * @property string $customer_id
 * @property string $token
 * @property int $position
 * @property string $status
 * @property \Illuminate\Support\Carbon $joined_at
 * @property \Illuminate\Support\Carbon|null $served_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $customer
 * @property-read \App\Models\Service $service
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueueEntry active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueueEntry newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueueEntry newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueueEntry query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueueEntry waiting()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueueEntry whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueueEntry whereCustomerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueueEntry whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueueEntry whereJoinedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueueEntry wherePosition($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueueEntry whereServedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueueEntry whereServiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueueEntry whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueueEntry whereToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QueueEntry whereUpdatedAt($value)
 */
	class QueueEntry extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $name
 * @property string|null $description
 * @property int $duration_minutes
 * @property int $max_queue_size
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Appointment> $appointments
 * @property-read int|null $appointments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\QueueEntry> $queueEntries
 * @property-read int|null $queue_entries_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\StaffAvailability> $staffAvailability
 * @property-read int|null $staff_availability_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\QueueEntry> $waitingQueue
 * @property-read int|null $waiting_queue_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service whereDurationMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service whereMaxQueueSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Service whereUpdatedAt($value)
 */
	class Service extends \Eloquent {}
}

namespace App\Models{
/**
 * @property-read \App\Models\Service|null $service
 * @property-read \App\Models\User|null $staff
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffAvailability active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffAvailability forDay(int $day)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffAvailability newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffAvailability newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffAvailability query()
 */
	class StaffAvailability extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string $role
 * @property string $password
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Appointment> $appointments
 * @property-read int|null $appointments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Appointment> $assignedAppointments
 * @property-read int|null $assigned_appointments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\StaffAvailability> $availability
 * @property-read int|null $availability_count
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\QueueEntry> $queueEntries
 * @property-read int|null $queue_entries_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 */
	class User extends \Eloquent {}
}

