<?php

namespace App\Services;

use App\Models\Room;
use App\Models\RoomAllocation;
use App\Models\Employee;
use App\Models\Guest;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RoomAllocationService
{
    /**
     * Allocate a room to an employee or guest
     *
     * @param array $data
     * @return RoomAllocation
     * @throws Exception
     */
    public function allocate(array $data): RoomAllocation
    {
        return DB::transaction(function () use ($data) {
            // Validate input
            $this->validateAllocationData($data);

            // Get the room
            $room = Room::findOrFail($data['room_id']);

            // Check room availability
            if (!$room->isAvailable()) {
                throw new Exception(
                    "Room {$room->room_code} is at full capacity ({$room->capacity} person(s)). " .
                    "Current occupancy: {$room->getCurrentOccupancy()}. Please select another room."
                );
            }

            // Validate either employee or guest (not both)
            if (isset($data['employee_id']) && isset($data['guest_id'])) {
                throw new Exception('Cannot allocate both employee and guest to the same allocation.');
            }

            if (!isset($data['employee_id']) && !isset($data['guest_id'])) {
                throw new Exception('Either employee or guest must be provided for allocation.');
            }

            // Check if employee/guest already has active allocation
            if (isset($data['employee_id'])) {
                $this->validateEmployeeAllocation($data['employee_id']);
            }

            if (isset($data['guest_id'])) {
                $this->validateGuestAllocation($data['guest_id']);
            }

            // Create the allocation
            $allocation = RoomAllocation::create([
                'room_id' => $data['room_id'],
                'employee_id' => $data['employee_id'] ?? null,
                'guest_id' => $data['guest_id'] ?? null,
                'allocated_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);

            // Update room status
            $this->updateRoomStatus($room);

            Log::info('Room allocated successfully', [
                'allocation_id' => $allocation->id,
                'room_code' => $room->room_code,
                'occupant_type' => $allocation->occupant_type,
            ]);

            return $allocation;
        });
    }

    /**
     * Release a room allocation
     *
     * @param RoomAllocation $allocation
     * @param array $data
     * @return void
     * @throws Exception
     */
    public function release(RoomAllocation $allocation, array $data = []): void
    {
        DB::transaction(function () use ($allocation, $data) {
            // Check if already released
            if ($allocation->isReleased()) {
                throw new Exception('This allocation has already been released.');
            }

            // Update allocation
            $allocation->update([
                'released_at' => now(),
                'notes' => isset($data['notes']) 
                    ? ($allocation->notes ? $allocation->notes . "\n" . $data['notes'] : $data['notes'])
                    : $allocation->notes,
            ]);

            // Update room status
            $this->updateRoomStatus($allocation->room);

            Log::info('Room allocation released successfully', [
                'allocation_id' => $allocation->id,
                'room_code' => $allocation->room->room_code,
                'duration_days' => $allocation->getDurationInDays(),
            ]);
        });
    }

    /**
     * Validate allocation data
     *
     * @param array $data
     * @return void
     * @throws Exception
     */
    private function validateAllocationData(array $data): void
    {
        if (!isset($data['room_id'])) {
            throw new Exception('Room ID is required.');
        }

        if (!Room::where('id', $data['room_id'])->exists()) {
            throw new Exception('Selected room does not exist.');
        }

        if (isset($data['employee_id']) && !Employee::where('id', $data['employee_id'])->exists()) {
            throw new Exception('Selected employee does not exist.');
        }

        if (isset($data['guest_id']) && !Guest::where('id', $data['guest_id'])->exists()) {
            throw new Exception('Selected guest does not exist.');
        }
    }

    /**
     * Validate employee allocation
     *
     * @param int $employeeId
     * @return void
     * @throws Exception
     */
    private function validateEmployeeAllocation(int $employeeId): void
    {
        $employee = Employee::findOrFail($employeeId);

        // Check if employee is active
        if (!$employee->isActive()) {
            throw new Exception(
                "Employee {$employee->name} ({$employee->employee_code}) is inactive and cannot be allocated to a room. " .
                "Please activate the employee first."
            );
        }

        // Check if employee already has active allocation
        $existingAllocation = $employee->activeAllocation;
        
        if ($existingAllocation) {
            $currentRoom = $existingAllocation->room;
            throw new Exception(
                "Employee {$employee->name} ({$employee->employee_code}) is already allocated to room {$currentRoom->room_code}. " .
                "Please release the current allocation first."
            );
        }
    }

    /**
     * Validate guest allocation
     *
     * @param int $guestId
     * @return void
     * @throws Exception
     */
    private function validateGuestAllocation(int $guestId): void
    {
        $guest = Guest::findOrFail($guestId);

        // Check if guest already has active allocation
        $existingAllocation = $guest->activeAllocation;
        
        if ($existingAllocation) {
            $currentRoom = $existingAllocation->room;
            throw new Exception(
                "Guest {$guest->name} is already allocated to room {$currentRoom->room_code}. " .
                "Please release the current allocation first."
            );
        }
    }

    /**
     * Update room status based on current occupancy
     *
     * @param Room $room
     * @return void
     */
    private function updateRoomStatus(Room $room): void
    {
        $occupancy = $room->getCurrentOccupancy();
        
        $newStatus = $occupancy > 0 ? Room::STATUS_OCCUPIED : Room::STATUS_EMPTY;
        
        if ($room->status !== $newStatus) {
            $room->update(['status' => $newStatus]);
            
            Log::debug('Room status updated', [
                'room_code' => $room->room_code,
                'old_status' => $room->status,
                'new_status' => $newStatus,
                'occupancy' => $occupancy,
            ]);
        }
    }

    /**
     * Get allocation summary
     *
     * @return array
     */
    public function getAllocationSummary(): array
    {
        $activeAllocations = RoomAllocation::active()->count();
        $employeeAllocations = RoomAllocation::active()->employeeAllocations()->count();
        $guestAllocations = RoomAllocation::active()->guestAllocations()->count();
        
        $totalCapacity = Room::sum('capacity');
        $availableSlots = $totalCapacity - $activeAllocations;
        $occupancyRate = $totalCapacity > 0 ? ($activeAllocations / $totalCapacity) * 100 : 0;

        return [
            'active_allocations' => $activeAllocations,
            'employee_allocations' => $employeeAllocations,
            'guest_allocations' => $guestAllocations,
            'total_capacity' => $totalCapacity,
            'available_slots' => $availableSlots,
            'occupancy_rate' => round($occupancyRate, 2),
        ];
    }

    /**
     * Find available rooms for allocation
     *
     * @param int|null $capacity
     * @return \Illuminate\Support\Collection
     */
    public function findAvailableRooms(?int $capacity = null)
    {
        $query = Room::query();

        if ($capacity) {
            $query->where('capacity', $capacity);
        }

        return $query->get()
            ->filter(fn($room) => $room->isAvailable())
            ->map(function ($room) {
                return [
                    'id' => $room->id,
                    'room_code' => $room->room_code,
                    'capacity' => $room->capacity,
                    'available_slots' => $room->getAvailableSlots(),
                    'current_occupancy' => $room->getCurrentOccupancy(),
                ];
            });
    }

    /**
     * Auto-assign room based on capacity preference
     *
     * @param int $preferredCapacity
     * @return Room|null
     */
    public function autoAssignRoom(int $preferredCapacity = 1): ?Room
    {
        // Try to find a room with preferred capacity
        $room = Room::where('capacity', $preferredCapacity)
            ->get()
            ->filter(fn($r) => $r->isAvailable())
            ->first();

        // If not found, try any available room
        if (!$room) {
            $room = Room::all()
                ->filter(fn($r) => $r->isAvailable())
                ->sortBy('capacity')
                ->first();
        }

        return $room;
    }

    /**
     * Transfer allocation to another room
     *
     * @param RoomAllocation $allocation
     * @param int $newRoomId
     * @return RoomAllocation
     * @throws Exception
     */
    public function transferAllocation(RoomAllocation $allocation, int $newRoomId): RoomAllocation
    {
        return DB::transaction(function () use ($allocation, $newRoomId) {
            if ($allocation->isReleased()) {
                throw new Exception('Cannot transfer a released allocation.');
            }

            $newRoom = Room::findOrFail($newRoomId);

            if (!$newRoom->isAvailable()) {
                throw new Exception("Room {$newRoom->room_code} is not available.");
            }

            if ($allocation->room_id === $newRoomId) {
                throw new Exception('Cannot transfer to the same room.');
            }

            $oldRoom = $allocation->room;

            // Release current allocation
            $this->release($allocation, [
                'notes' => "Transferred to room {$newRoom->room_code}"
            ]);

            // Create new allocation
            $newAllocation = $this->allocate([
                'room_id' => $newRoomId,
                'employee_id' => $allocation->employee_id,
                'guest_id' => $allocation->guest_id,
                'notes' => "Transferred from room {$oldRoom->room_code}",
            ]);

            Log::info('Allocation transferred', [
                'old_allocation_id' => $allocation->id,
                'new_allocation_id' => $newAllocation->id,
                'from_room' => $oldRoom->room_code,
                'to_room' => $newRoom->room_code,
            ]);

            return $newAllocation;
        });
    }

    /**
     * Get occupancy report for a date range
     *
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getOccupancyReport(string $startDate, string $endDate): array
    {
        $allocations = RoomAllocation::whereBetween('allocated_at', [$startDate, $endDate])
            ->with(['room', 'employee', 'guest'])
            ->get();

        $totalAllocations = $allocations->count();
        $employeeAllocations = $allocations->where('employee_id', '!=', null)->count();
        $guestAllocations = $allocations->where('guest_id', '!=', null)->count();
        
        $releasedAllocations = $allocations->where('released_at', '!=', null);
        $averageDuration = $releasedAllocations->avg(fn($a) => $a->getDurationInDays());

        return [
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'summary' => [
                'total_allocations' => $totalAllocations,
                'employee_allocations' => $employeeAllocations,
                'guest_allocations' => $guestAllocations,
                'released_allocations' => $releasedAllocations->count(),
                'active_allocations' => $totalAllocations - $releasedAllocations->count(),
                'average_duration_days' => round($averageDuration ?? 0, 1),
            ],
            'by_room_capacity' => $allocations->groupBy('room.capacity')
                ->map(fn($group) => $group->count())
                ->toArray(),
        ];
    }
}