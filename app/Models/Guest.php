<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Guest extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'phone',
        'company',
        'visit_date',
        'checkout_date',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'visit_date' => 'date',
        'checkout_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get all allocations for this guest
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
     * Check if guest has an active allocation
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
     * Check if guest is currently visiting
     */
    public function isCurrentlyVisiting(): bool
    {
        $today = Carbon::today();
        $visitDate = Carbon::parse($this->visit_date);
        $checkoutDate = $this->checkout_date ? Carbon::parse($this->checkout_date) : null;

        if ($checkoutDate) {
            return $today->between($visitDate, $checkoutDate);
        }

        return $today->isSameDay($visitDate) || $today->isAfter($visitDate);
    }

    /**
     * Get visit duration in days
     */
    public function getVisitDuration(): ?int
    {
        if (!$this->checkout_date) {
            return null;
        }

        return Carbon::parse($this->visit_date)
            ->diffInDays(Carbon::parse($this->checkout_date)) + 1;
    }

    /**
     * Scope to get current guests (visiting today)
     */
    public function scopeCurrent($query)
    {
        return $query->where('visit_date', '<=', now())
            ->where(function($q) {
                $q->whereNull('checkout_date')
                  ->orWhere('checkout_date', '>=', now());
            });
    }

    /**
     * Scope to get upcoming guests
     */
    public function scopeUpcoming($query)
    {
        return $query->where('visit_date', '>', now());
    }

    /**
     * Scope to get past guests
     */
    public function scopePast($query)
    {
        return $query->where(function($q) {
            // Kondisi 1: checkout_date sudah lewat dari hari ini
            $q->where('checkout_date', '<', now())
            // ATAU Kondisi 2: checkout_date kosong DAN visit_date sudah lebih dari 7 hari yang lalu
            ->orWhere(function($sub) {
                    $sub->whereNull('checkout_date')
                        ->where('visit_date', '<', now()->subDays(7));
            });
        });
    }

    /**
     * Get formatted visit date
     */
    public function getFormattedVisitDateAttribute(): string
    {
        return $this->visit_date->format('d M Y');
    }

    /**
     * Get formatted checkout date
     */
    public function getFormattedCheckoutDateAttribute(): ?string
    {
        return $this->checkout_date?->format('d M Y');
    }

    /**
     * Get guest display name
     */
    public function getDisplayNameAttribute(): string
    {
        $company = $this->company ? " - {$this->company}" : '';
        return "{$this->name}{$company}";
    }

    /**
     * Get full info string
     */
    public function getFullInfoAttribute(): string
    {
        return "{$this->name} (Visit: {$this->formatted_visit_date})";
    }
}