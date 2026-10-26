<?php

namespace App\Models;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property int|null $department_id
 * @property int|null $faculty_id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property UserStatus $status
 * @property Carbon|null $last_login_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'department_id', 'faculty_id', 'status', 'email_verified_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, LogsActivity, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'last_login_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'status', 'department_id', 'faculty_id'])
            ->logOnlyDirty();
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * A Dean's faculty. Other users have none.
     *
     * @return BelongsTo<Faculty, $this>
     */
    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    /**
     * The user's staff role that matters for forwarding and for which
     * portal they use. Null for students.
     */
    public function staffRole(): ?RoleName
    {
        foreach ([RoleName::SuperAdmin, RoleName::AdminOfficer, RoleName::Hod, RoleName::Dean] as $role) {
            if ($this->hasRole($role->value)) {
                return $role;
            }
        }

        return null;
    }

    public function isReviewer(): bool
    {
        return $this->hasAnyRole([RoleName::Hod->value, RoleName::Dean->value]);
    }

    /**
     * @return HasOne<Student, $this>
     */
    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    /**
     * @return HasMany<ApplicationMessage, $this>
     */
    public function sentMessages(): HasMany
    {
        return $this->hasMany(ApplicationMessage::class, 'sender_id');
    }

    /**
     * @return HasMany<ApplicationAttachment, $this>
     */
    public function uploadedAttachments(): HasMany
    {
        return $this->hasMany(ApplicationAttachment::class, 'uploaded_by');
    }

    /**
     * @return HasMany<InternalNote, $this>
     */
    public function internalNotes(): HasMany
    {
        return $this->hasMany(InternalNote::class, 'admin_id');
    }

    /**
     * @return HasMany<Announcement, $this>
     */
    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class, 'created_by');
    }

    /**
     * @return HasMany<ApplicationEvent, $this>
     */
    public function applicationEvents(): HasMany
    {
        return $this->hasMany(ApplicationEvent::class);
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }

    /**
     * Every Admin Officer in the given department — who to notify about
     * department-scoped application activity.
     *
     * @return EloquentCollection<int, User>
     */
    public static function adminOfficersInDepartment(int $departmentId): EloquentCollection
    {
        return User::query()
            ->where('department_id', $departmentId)
            ->role(RoleName::AdminOfficer->value)
            ->get();
    }

    /**
     * The one active HOD of a department, who receives its forwards.
     */
    public static function activeHodForDepartment(int $departmentId): ?self
    {
        return User::query()
            ->where('department_id', $departmentId)
            ->where('status', UserStatus::Active)
            ->role(RoleName::Hod->value)
            ->first();
    }

    /**
     * The one active Dean of a faculty, who receives its forwards.
     */
    public static function activeDeanForFaculty(?int $facultyId): ?self
    {
        if ($facultyId === null) {
            return null;
        }

        return User::query()
            ->where('faculty_id', $facultyId)
            ->where('status', UserStatus::Active)
            ->role(RoleName::Dean->value)
            ->first();
    }

    /**
     * Every active student in the given department — who to notify about
     * department-wide news such as a new timetable.
     *
     * @return EloquentCollection<int, User>
     */
    public static function activeStudentsInDepartment(int $departmentId): EloquentCollection
    {
        return User::query()
            ->where('department_id', $departmentId)
            ->where('status', UserStatus::Active)
            ->role(RoleName::Student->value)
            ->get();
    }
}
