<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'employee_code',
        'name',
        'department',
        'status',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Available departments
     */
    public const DEPARTMENTS = [
        'HR' => 'HR',
        'Finance' => 'Finance',
        'Produksi' => 'Produksi',
        'Sarana' => 'Sarana',
        'Safety' => 'Safety',
    ];

    /**
     * Available statuses
     */
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    public const STATUSES = [
        self::STATUS_ACTIVE => 'Active',
        self::STATUS_INACTIVE => 'Inactive',
    ];

    /**
     * Get all allocations for this employee
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(RoomAllocation::class);
    }

    /**
     * Get the current active allocation
     */
    public function activeAllocation()
    {
        return $this->hasOne(RoomAllocation::class)
            ->whereNull('released_at')
            ->latest('allocated_at');
    }

    /**
     * Get historical allocations
     */
    public function historicalAllocations(): HasMany
    {
        return $this->hasMany(RoomAllocation::class)
            ->whereNotNull('released_at')
            ->orderBy('allocated_at', 'desc');
    }

    /**
     * Check if employee has an active allocation
     */
    public function hasActiveAllocation(): bool
    {
        return $this->activeAllocation()->exists();
    }

    /**
     * Get the current room if allocated
     */
    public function getCurrentRoom()
    {
        return $this->activeAllocation?->room;
    }

    /**
     * Check if employee is active
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Scope to get only active employees
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope to get only inactive employees
     */
    public function scopeInactive($query)
    {
        return $query->where('status', self::STATUS_INACTIVE);
    }

    /**
     * Scope to filter by department
     */
    public function scopeByDepartment($query, string $department)
    {
        return $query->where('department', $department);
    }

    /**
     * Get formatted department name
     */
    public function getDepartmentNameAttribute(): string
    {
        return self::DEPARTMENTS[$this->department] ?? $this->department;
    }

    /**
     * Get formatted status name
     */
    public function getStatusNameAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Get full display name with employee code
     */
    public function getFullDisplayNameAttribute(): string
    {
        return "{$this->name} ({$this->employee_code})";
    }
}