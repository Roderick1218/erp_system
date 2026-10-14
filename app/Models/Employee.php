<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use Audited, SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'dob' => 'date',
        'hire_date' => 'date',
        'resign_date' => 'date',
        'passport_expiry' => 'date',
        'work_permit_expiry' => 'date',
        'cidb_expiry' => 'date',
        'gdl_expiry' => 'date',
        'basic_salary' => 'decimal:2',
        'fixed_allowance' => 'decimal:2',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->nickname ? "{$this->name} ({$this->nickname})" : $this->name;
    }

    public function getAgeAttribute(): ?int
    {
        return $this->dob?->age;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /** expiry column => [label, number column] */
    public const DOCUMENTS = [
        'passport_expiry' => ['Passport', 'passport_no'],
        'work_permit_expiry' => ['Work permit', 'work_permit_no'],
        'cidb_expiry' => ['CIDB card', 'cidb_no'],
        'gdl_expiry' => ['GDL licence', 'gdl_no'],
    ];

    public const EMPLOYMENT_TYPES = ['permanent' => 'Permanent', 'contract' => 'Contract staff', 'foreign_worker' => 'Foreign worker', 'intern' => 'Intern'];

    /** Documents expired or expiring within $days: [label => expiry date]. */
    public function expiringDocuments(int $days = 30): array
    {
        $limit = now()->startOfDay()->addDays($days);
        $out = [];
        foreach (self::DOCUMENTS as $col => [$label]) {
            if ($this->{$col} && $this->{$col}->lte($limit)) {
                $out[$label] = $this->{$col};
            }
        }

        return $out;
    }

    /** 'expired' | 'expiring' (≤30 days) | 'active' | null for one document column. */
    public function documentStatus(string $col): ?string
    {
        $date = $this->{$col};
        if (! $date) {
            return null;
        }
        if ($date->lt(now()->startOfDay())) {
            return 'expired';
        }

        return $date->lte(now()->startOfDay()->addDays(30)) ? 'expiring' : 'active';
    }

    public function getInitialsAttribute(): string
    {
        $parts = preg_split('/\s+/', trim($this->nickname ?: $this->name));

        return strtoupper(mb_substr($parts[0] ?? '', 0, 1).mb_substr($parts[1] ?? '', 0, 1));
    }
}
