<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoomRequest;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class RoomController extends Controller
{
    /**
     * Display the room management page
     */
    public function index()
    {
        try {
            $capacities = Room::CAPACITIES;
            $statuses = Room::STATUSES;
            
            return view('rooms.index', compact('capacities', 'statuses'));
        } catch (Exception $e) {
            Log::error('Error loading room index: ' . $e->getMessage());
            return back()->with('error', 'Failed to load room page.');
        }
    }

    /**
     * Get all rooms data for DataGrid
     */
    public function data(): JsonResponse
    {
        try {
            $rooms = Room::withCount('activeAllocations')
                ->orderBy('room_code')
                ->get()
                ->map(function ($room) {
                    return [
                        'id' => $room->id,
                        'room_code' => $room->room_code,
                        'capacity' => $room->capacity,
                        'capacity_text' => $room->capacity_text,
                        'status' => $room->status,
                        'status_text' => $room->status_text,
                        'current_occupancy' => $room->getCurrentOccupancy(),
                        'available_slots' => $room->getAvailableSlots(),
                        'occupancy_percentage' => round($room->getOccupancyPercentage(), 1),
                        'is_available' => $room->isAvailable(),
                        'notes' => $room->notes,
                        'created_at' => $room->created_at->format('Y-m-d H:i:s'),
                    ];
                });

            return response()->json($rooms);

        } catch (Exception $e) {
            Log::error('Error fetching room data: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to fetch room data.'
            ], 500);
        }
    }

    /**
     * Store a new room
     */
    public function store(RoomRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $room = Room::create(array_merge(
                $request->validated(),
                ['status' => Room::STATUS_EMPTY]
            ));

            DB::commit();

            Log::info('Room created', [
                'room_id' => $room->id,
                'room_code' => $room->room_code,
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'message' => 'Room created successfully.',
                'data' => $room
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error creating room: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'Failed to create room. Please try again.'
            ], 500);
        }
    }

    /**
     * Display the specified room
     */
    public function show(Room $room): JsonResponse
    {
        try {
            $room->load([
                'activeAllocations.employee',
                'activeAllocations.guest',
                'historicalAllocations' => function($query) {
                    $query->limit(10)->orderBy('released_at', 'desc');
                }
            ]);

            return response()->json([
                'data' => $room,
                'current_occupancy' => $room->getCurrentOccupancy(),
                'available_slots' => $room->getAvailableSlots(),
                'is_available' => $room->isAvailable(),
                'current_occupants' => $room->getCurrentOccupants(),
            ]);

        } catch (Exception $e) {
            Log::error('Error fetching room: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to fetch room details.'
            ], 500);
        }
    }

    /**
     * Update the specified room
     */
    public function update(RoomRequest $request, Room $room): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Check if capacity is being reduced below current occupancy
            $newCapacity = $request->capacity;
            $currentOccupancy = $room->getCurrentOccupancy();

            if ($newCapacity < $currentOccupancy) {
                return response()->json([
                    'error' => "Cannot reduce capacity to {$newCapacity}. Current occupancy is {$currentOccupancy}. Please release some allocations first."
                ], 422);
            }

            $room->update($request->validated());

            DB::commit();

            Log::info('Room updated', [
                'room_id' => $room->id,
                'room_code' => $room->room_code,
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'message' => 'Room updated successfully.',
                'data' => $room
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error updating room: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'Failed to update room. Please try again.'
            ], 500);
        }
    }

    /**
     * Remove the specified room
     */
    public function destroy(Room $room): JsonResponse
    {
        try {
            // Check if room has active allocations
            if (!$room->isEmpty()) {
                return response()->json([
                    'error' => 'Cannot delete room with active allocations. Please release all allocations first.'
                ], 422);
            }

            DB::beginTransaction();

            $roomCode = $room->room_code;
            $room->delete();

            DB::commit();

            Log::info('Room deleted', [
                'room_code' => $roomCode,
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'message' => 'Room deleted successfully.'
            ], 200);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error deleting room: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'Failed to delete room. Please try again.'
            ], 500);
        }
    }

    /**
     * Get pivot grid data for room analysis
     */
    public function pivotData(): JsonResponse
    {
        try {
            $data = Room::with('activeAllocations')
                ->get()
                ->map(function ($room) {
                    return [
                        'room_code' => $room->room_code,
                        'capacity' => $room->capacity,
                        'capacity_text' => $room->capacity_text,
                        'occupancy' => $room->getCurrentOccupancy(),
                        'available' => $room->getAvailableSlots(),
                        'status' => $room->status_text,
                        'occupancy_percentage' => round($room->getOccupancyPercentage(), 1),
                    ];
                });

            return response()->json($data);

        } catch (Exception $e) {
            Log::error('Error fetching pivot data: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to fetch pivot data.'
            ], 500);
        }
    }

    /**
     * Get room statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $totalRooms = Room::count();
            $occupiedRooms = Room::occupied()->count();
            $emptyRooms = Room::empty()->count();
            
            $capacityStats = Room::selectRaw('capacity, COUNT(*) as count')
                ->groupBy('capacity')
                ->get()
                ->keyBy('capacity');

            $stats = [
                'total_rooms' => $totalRooms,
                'occupied_rooms' => $occupiedRooms,
                'empty_rooms' => $emptyRooms,
                'occupancy_rate' => $totalRooms > 0 ? round(($occupiedRooms / $totalRooms) * 100, 1) : 0,
                'capacity_1_rooms' => $capacityStats->get(1)?->count ?? 0,
                'capacity_2_rooms' => $capacityStats->get(2)?->count ?? 0,
                'total_capacity' => Room::sum('capacity'),
                'total_occupied_slots' => DB::table('room_allocations')
                    ->whereNull('released_at')
                    ->count(),
            ];

            return response()->json($stats);

        } catch (Exception $e) {
            Log::error('Error fetching room statistics: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to fetch statistics.'
            ], 500);
        }
    }

    /**
     * Get available rooms for allocation
     */
    public function availableRooms(): JsonResponse
    {
        try {
            $rooms = Room::all()
                ->filter(function ($room) {
                    return $room->isAvailable();
                })
                ->map(function ($room) {
                    return [
                        'id' => $room->id,
                        'room_code' => $room->room_code,
                        'capacity' => $room->capacity,
                        'available_slots' => $room->getAvailableSlots(),
                        'display_text' => "{$room->room_code} (Available: {$room->getAvailableSlots()}/{$room->capacity})"
                    ];
                })
                ->values();

            return response()->json($rooms);

        } catch (Exception $e) {
            Log::error('Error fetching available rooms: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to fetch available rooms.'
            ], 500);
        }
    }
}
