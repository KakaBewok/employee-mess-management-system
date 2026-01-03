<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class RoomAllocation extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'room_id',
        'employee_id',
        'guest_id',
        'allocated_at',
        'released_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'allocated_at' => 'datetime',
        'released_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the room for this allocation
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * Get the employee for this allocation
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the guest for this allocation
     */
    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    /**
     * Get occupant name (either employee or guest)
     */
    public function getOccupantNameAttribute(): string
    {
        if ($this->employee_id && $this->employee) {
            return $this->employee->name;
        }
        
        if ($this->guest_id && $this->guest) {
            return $this->guest->name;
        }
        
        return 'Unknown';
    }

    /**
     * Get occupant type
     */
    public function getOccupantTypeAttribute(): string
    {
        return $this->employee_id ? 'Employee' : 'Guest';
    }

    /**
     * Get occupant full info
     */
    public function getOccupantInfoAttribute(): string
    {
        if ($this->employee_id && $this->employee) {
            return "{$this->employee->name} ({$this->employee->employee_code})";
        }
        
        if ($this->guest_id && $this->guest) {
            $company = $this->guest->company ? " - {$this->guest->company}" : '';
            return "{$this->guest->name}{$company}";
        }
        
        return 'Unknown';
    }

    /**
     * Check if allocation is active
     */
    public function isActive(): bool
    {
        return is_null($this->released_at);
    }

    /**
     * Check if allocation is released
     */
    public function isReleased(): bool
    {
        return !is_null($this->released_at);
    }

    /**
     * Get allocation duration in days
     */
    public function getDurationInDays(): ?int
    {
        if (!$this->released_at) {
            return Carbon::parse($this->allocated_at)->diffInDays(now());
        }

        return Carbon::parse($this->allocated_at)
            ->diffInDays(Carbon::parse($this->released_at));
    }

    /**
     * Get allocation duration in hours
     */
    public function getDurationInHours(): ?int
    {
        if (!$this->released_at) {
            return Carbon::parse($this->allocated_at)->diffInHours(now());
        }

        return Carbon::parse($this->allocated_at)
            ->diffInHours(Carbon::parse($this->released_at));
    }

    /**
     * Scope to get active allocations
     */
    public function scopeActive($query)
    {
        return $query->whereNull('released_at');
    }

    /**
     * Scope to get released allocations
     */
    public function scopeReleased($query)
    {
        return $query->whereNotNull('released_at');
    }

    /**
     * Scope to get allocations by room
     */
    public function scopeByRoom($query, int $roomId)
    {
        return $query->where('room_id', $roomId);
    }

    /**
     * Scope to get employee allocations
     */
    public function scopeEmployeeAllocations($query)
    {
        return $query->whereNotNull('employee_id');
    }

    /**
     * Scope to get guest allocations
     */
    public function scopeGuestAllocations($query)
    {
        return $query->whereNotNull('guest_id');
    }

    /**
     * Get formatted allocated date
     */
    public function getFormattedAllocatedAtAttribute(): string
    {
        return $this->allocated_at->format('d M Y H:i');
    }

    /**
     * Get formatted released date
     */
    public function getFormattedReleasedAtAttribute(): ?string
    {
        return $this->released_at?->format('d M Y H:i');
    }

    /**
     * Boot method to add model events
     */
    protected static function booted()
    {
        // Validate before creating
        static::creating(function ($allocation) {
            // Ensure either employee_id or guest_id is set, but not both
            if ((!$allocation->employee_id && !$allocation->guest_id) ||
                ($allocation->employee_id && $allocation->guest_id)) {
                throw new \Exception('Either employee_id or guest_id must be set, but not both.');
            }
        });
    }
}