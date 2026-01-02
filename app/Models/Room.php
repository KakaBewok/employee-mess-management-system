<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Room extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'room_code',
        'capacity',
        'status',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'capacity' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Available statuses
     */
    public const STATUS_EMPTY = 'empty';
    public const STATUS_OCCUPIED = 'occupied';

    public const STATUSES = [
        self::STATUS_EMPTY => 'Empty',
        self::STATUS_OCCUPIED => 'Occupied',
    ];

    /**
     * Available capacities
     */
    public const CAPACITY_ONE = 1;
    public const CAPACITY_TWO = 2;

    public const CAPACITIES = [
        self::CAPACITY_ONE => '1 Person',
        self::CAPACITY_TWO => '2 Persons',
    ];

    /**
     * Get all allocations for this room
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(RoomAllocation::class);
    }

    /**
     * Get current active allocations
     */
    public function activeAllocations(): HasMany
    {
        return $this->hasMany(RoomAllocation::class)
            ->whereNull('released_at')
            ->orderBy('allocated_at', 'desc');
    }

    /**
     * Get historical allocations
     */
    public function historicalAllocations(): HasMany
    {
        return $this->hasMany(RoomAllocation::class)
            ->whereNotNull('released_at')
            ->orderBy('released_at', 'desc');
    }

    /**
     * Get current occupancy count
     */
    public function getCurrentOccupancy(): int
    {
        return $this->activeAllocations()->count();
    }

    /**
     * Get available slots
     */
    public function getAvailableSlots(): int
    {
        return max(0, $this->capacity - $this->getCurrentOccupancy());
    }

    /**
     * Check if room is available for allocation
     */
    public function isAvailable(): bool
    {
        return $this->getAvailableSlots() > 0;
    }

    /**
     * Check if room is full
     */
    public function isFull(): bool
    {
        return $this->getCurrentOccupancy() >= $this->capacity;
    }

    /**
     * Check if room is empty
     */
    public function isEmpty(): bool
    {
        return $this->getCurrentOccupancy() === 0;
    }

    /**
     * Get occupancy percentage
     */
    public function getOccupancyPercentage(): float
    {
        if ($this->capacity === 0) {
            return 0;
        }
        return ($this->getCurrentOccupancy() / $this->capacity) * 100;
    }

    /**
     * Scope to get available rooms
     */
    public function scopeAvailable($query)
    {
        return $query->whereHas('activeAllocations', function($q) {
            // Count active allocations
        }, '<', DB::raw('capacity'));
    }

    /**
     * Scope to get occupied rooms
     */
    public function scopeOccupied($query)
    {
        return $query->where('status', self::STATUS_OCCUPIED);
    }

    /**
     * Scope to get empty rooms
     */
    public function scopeEmpty($query)
    {
        return $query->where('status', self::STATUS_EMPTY);
    }

    /**
     * Scope to filter by capacity
     */
    public function scopeByCapacity($query, int $capacity)
    {
        return $query->where('capacity', $capacity);
    }

    /**
     * Get formatted capacity
     */
    public function getCapacityTextAttribute(): string
    {
        return self::CAPACITIES[$this->capacity] ?? "{$this->capacity} Person(s)";
    }

    /**
     * Get formatted status
     */
    public function getStatusTextAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Get room display name
     */
    public function getDisplayNameAttribute(): string
    {
        return "{$this->room_code} (Cap: {$this->capacity})";
    }

    /**
     * Get current occupants
     */
    public function getCurrentOccupants()
    {
        return $this->activeAllocations->map(function($allocation) {
            return [
                'type' => $allocation->occupant_type,
                'name' => $allocation->occupant_name,
                'allocated_at' => $allocation->allocated_at,
            ];
        });
    }
}
