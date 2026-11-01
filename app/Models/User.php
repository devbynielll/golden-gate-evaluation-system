<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    // account roles
    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLE_DEPARTMENT_ADMIN = 'department_admin';

    public const ROLE_OFFICE_ADMIN = 'office_admin';

    public const ROLE_STUDENT = 'student';

    // roles with admin access
    public const ADMIN_ROLES = [self::ROLE_SUPER_ADMIN, self::ROLE_DEPARTMENT_ADMIN, self::ROLE_OFFICE_ADMIN];

    // managed roles; Department Admin remains a singleton, Office Admin is assigned per office
    public const MANAGED_ROLES = [self::ROLE_DEPARTMENT_ADMIN, self::ROLE_OFFICE_ADMIN];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'id_number',
        'office_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

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
        ];
    }

    // any admin account
    public function isAdmin(): bool
    {
        return in_array($this->role, self::ADMIN_ROLES, true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function isDepartmentAdmin(): bool
    {
        return $this->role === self::ROLE_DEPARTMENT_ADMIN;
    }

    public function isOfficeAdmin(): bool
    {
        return $this->role === self::ROLE_OFFICE_ADMIN;
    }

    public function isStudent(): bool
    {
        return $this->role === self::ROLE_STUDENT;
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function assignedOfficeOrAbort(): Office
    {
        // Read fresh ownership on each protected request, never from an old session/relation cache.
        $id = static::whereKey($this->id)->where('role', self::ROLE_OFFICE_ADMIN)->value('office_id');
        $office = $id ? Office::find($id) : null;
        abort_unless($office, 403, 'Your account has no office assignment. Ask the Super Admin to assign an office.');

        $this->setAttribute('office_id', $office->id);
        $this->unsetRelation('office');

        return $office;
    }

    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }
}
