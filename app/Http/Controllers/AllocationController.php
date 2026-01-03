<?php

namespace App\Http\Controllers;

use App\Http\Requests\AllocationRequest;
use App\Http\Requests\ReleaseAllocationRequest;
use App\Models\Employee;
use App\Models\Guest;
use App\Models\Room;
use App\Models\RoomAllocation;
use App\Services\RoomAllocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class AllocationController extends Controller
{
    protected RoomAllocationService $allocationService;

    public function __construct(RoomAllocationService $allocationService)
    {
        $this->allocationService = $allocationService;
    }

    /**
     * Display the allocation management page
     */
    public function index()
    {
        try {
            // Get available rooms (with available slots)
            $rooms = Room::all()
                ->filter(fn($room) => $room->isAvailable())
                ->map(function ($room) {
                    return [
                        'id' => $room->id,
                        'display_text' => "{$room->room_code} (Available: {$room->getAvailableSlots()}/{$room->capacity})"
                    ];
                })
                ->values();

            // Get active employees without allocation
            $employees = Employee::active()
                ->whereDoesntHave('activeAllocation')
                ->orderBy('name')
                ->get()
                ->map(function ($employee) {
                    return [
                        'id' => $employee->id,
                        'display_text' => "{$employee->name} ({$employee->employee_code}) - {$employee->department}"
                    ];
                });

            // Get current guests without allocation
            $guests = Guest::current()
                ->whereDoesntHave('activeAllocation')
                ->orderBy('name')
                ->get()
                ->map(function ($guest) {
                    return [
                        'id' => $guest->id,
                        'display_text' => "{$guest->name}" . ($guest->company ? " - {$guest->company}" : "")
                    ];
                });

            return view('allocations.index', compact('rooms', 'employees', 'guests'));

        } catch (Exception $e) {
            Log::error('Error loading allocation index: ' . $e->getMessage());
            return back()->with('error', 'Failed to load allocation page.');
        }
    }

    /**
     * Get all active allocations data
     */
    public function data(): JsonResponse
    {
        try {
            $allocations = RoomAllocation::with(['room', 'employee', 'guest'])
                ->active()
                ->orderBy('allocated_at', 'desc')
                ->get()
                ->map(function ($allocation) {
                    return [
                        'id' => $allocation->id,
                        'room_code' => $allocation->room->room_code,
                        'room_capacity' => $allocation->room->capacity,
                        'occupant_name' => $allocation->occupant_name,
                        'occupant_info' => $allocation->occupant_info,
                        'occupant_type' => $allocation->occupant_type,
                        'allocated_at' => $allocation->allocated_at->format('Y-m-d H:i:s'),
                        'duration_days' => $allocation->getDurationInDays(),
                        'duration_hours' => $allocation->getDurationInHours(),
                        'notes' => $allocation->notes,
                    ];
                });

            return response()->json($allocations);

        } catch (Exception $e) {
            Log::error('Error fetching allocation data: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to fetch allocation data.'
            ], 500);
        }
    }

    /**
     * Get allocation history
     */
    public function history(Request $request): JsonResponse
    {
        try {
            $query = RoomAllocation::with(['room', 'employee', 'guest'])
                ->released()
                ->orderBy('released_at', 'desc');

            // Filter by date range if provided
            if ($request->has('start_date')) {
                $query->where('allocated_at', '>=', $request->start_date);
            }

            if ($request->has('end_date')) {
                $query->where('released_at', '<=', $request->end_date);
            }

            // Filter by room
            if ($request->has('room_id')) {
                $query->where('room_id', $request->room_id);
            }

            // Filter by type (employee or guest)
            if ($request->has('type')) {
                if ($request->type === 'employee') {
                    $query->employeeAllocations();
                } elseif ($request->type === 'guest') {
                    $query->guestAllocations();
                }
            }

            $allocations = $query->paginate($request->per_page ?? 20);

            return response()->json($allocations);

        } catch (Exception $e) {
            Log::error('Error fetching allocation history: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to fetch allocation history.'
            ], 500);
        }
    }

    /**
     * Store a new room allocation
     */
    public function store(AllocationRequest $request): JsonResponse
    {
        try {
            $allocation = $this->allocationService->allocate($request->validated());

            $allocation->load(['room', 'employee', 'guest']);

            Log::info('Allocation created', [
                'allocation_id' => $allocation->id,
                'room_id' => $allocation->room_id,
                'occupant_type' => $allocation->occupant_type,
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'message' => 'Room allocated successfully.',
                'data' => [
                    'id' => $allocation->id,
                    'room_code' => $allocation->room->room_code,
                    'occupant_name' => $allocation->occupant_name,
                    'occupant_type' => $allocation->occupant_type,
                    'allocated_at' => $allocation->allocated_at->format('Y-m-d H:i:s'),
                ]
            ], 201);

        } catch (Exception $e) {
            Log::error('Error creating allocation: ' . $e->getMessage());
            
            return response()->json([
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Display the specified allocation
     */
    public function show(RoomAllocation $allocation): JsonResponse
    {
        try {
            $allocation->load(['room', 'employee', 'guest']);

            return response()->json([
                'data' => $allocation,
                'is_active' => $allocation->isActive(),
                'duration_days' => $allocation->getDurationInDays(),
                'duration_hours' => $allocation->getDurationInHours(),
            ]);

        } catch (Exception $e) {
            Log::error('Error fetching allocation: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to fetch allocation details.'
            ], 500);
        }
    }

    /**
     * Release a room allocation
     */
    public function release(ReleaseAllocationRequest $request, RoomAllocation $allocation): JsonResponse
    {
        try {
            $this->allocationService->release($allocation, $request->validated());

            Log::info('Allocation released', [
                'allocation_id' => $allocation->id,
                'room_id' => $allocation->room_id,
                'occupant_type' => $allocation->occupant_type,
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'message' => 'Room allocation released successfully.',
                'data' => [
                    'id' => $allocation->id,
                    'released_at' => $allocation->released_at->format('Y-m-d H:i:s'),
                    'duration_days' => $allocation->getDurationInDays(),
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Error releasing allocation: ' . $e->getMessage());
            
            return response()->json([
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Bulk release allocations
     */
    public function bulkRelease(Request $request): JsonResponse
    {
        $request->validate([
            'allocation_ids' => 'required|array',
            'allocation_ids.*' => 'exists:room_allocations,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            DB::beginTransaction();

            $releasedCount = 0;
            $errors = [];

            foreach ($request->allocation_ids as $id) {
                try {
                    $allocation = RoomAllocation::findOrFail($id);
                    
                    if ($allocation->isActive()) {
                        $this->allocationService->release($allocation, [
                            'notes' => $request->notes
                        ]);
                        $releasedCount++;
                    }
                } catch (Exception $e) {
                    $errors[] = "Allocation ID {$id}: " . $e->getMessage();
                }
            }

            DB::commit();

            Log::info('Bulk release completed', [
                'released_count' => $releasedCount,
                'total_requested' => count($request->allocation_ids),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'message' => "{$releasedCount} allocation(s) released successfully.",
                'released_count' => $releasedCount,
                'errors' => $errors
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error in bulk release: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'Failed to release allocations. Please try again.'
            ], 500);
        }
    }

    /**
     * Get allocation statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $activeAllocations = RoomAllocation::active()->count();
            $employeeAllocations = RoomAllocation::active()->employeeAllocations()->count();
            $guestAllocations = RoomAllocation::active()->guestAllocations()->count();
            
            $totalCapacity = Room::sum('capacity');
            $occupiedSlots = $activeAllocations;
            $availableSlots = $totalCapacity - $occupiedSlots;
            $occupancyRate = $totalCapacity > 0 ? round(($occupiedSlots / $totalCapacity) * 100, 1) : 0;

            // Allocations by room capacity
            $allocationsByCapacity = DB::table('room_allocations')
                ->join('rooms', 'room_allocations.room_id', '=', 'rooms.id')
                ->whereNull('room_allocations.released_at')
                ->select('rooms.capacity', DB::raw('COUNT(*) as count'))
                ->groupBy('rooms.capacity')
                ->get()
                ->keyBy('capacity');

            $stats = [
                'active_allocations' => $activeAllocations,
                'employee_allocations' => $employeeAllocations,
                'guest_allocations' => $guestAllocations,
                'total_capacity' => $totalCapacity,
                'occupied_slots' => $occupiedSlots,
                'available_slots' => $availableSlots,
                'occupancy_rate' => $occupancyRate,
                'capacity_1_allocations' => $allocationsByCapacity->get(1)?->count ?? 0,
                'capacity_2_allocations' => $allocationsByCapacity->get(2)?->count ?? 0,
                'historical_total' => RoomAllocation::released()->count(),
            ];

            return response()->json($stats);

        } catch (Exception $e) {
            Log::error('Error fetching allocation statistics: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to fetch statistics.'
            ], 500);
        }
    }

    /**
     * Get allocation report
     */
    public function report(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'type' => 'nullable|in:employee,guest',
        ]);

        try {
            $query = RoomAllocation::with(['room', 'employee', 'guest'])
                ->whereBetween('allocated_at', [$request->start_date, $request->end_date]);

            if ($request->type === 'employee') {
                $query->employeeAllocations();
            } elseif ($request->type === 'guest') {
                $query->guestAllocations();
            }

            $allocations = $query->get();

            $report = [
                'period' => [
                    'start_date' => $request->start_date,
                    'end_date' => $request->end_date,
                ],
                'summary' => [
                    'total_allocations' => $allocations->count(),
                    'active_allocations' => $allocations->where('released_at', null)->count(),
                    'released_allocations' => $allocations->where('released_at', '!=', null)->count(),
                    'employee_allocations' => $allocations->where('employee_id', '!=', null)->count(),
                    'guest_allocations' => $allocations->where('guest_id', '!=', null)->count(),
                ],
                'average_duration' => $allocations->where('released_at', '!=', null)
                    ->avg(fn($a) => $a->getDurationInDays()),
                'allocations' => $allocations->map(function ($allocation) {
                    return [
                        'room_code' => $allocation->room->room_code,
                        'occupant' => $allocation->occupant_info,
                        'type' => $allocation->occupant_type,
                        'allocated_at' => $allocation->allocated_at->format('Y-m-d H:i'),
                        'released_at' => $allocation->released_at?->format('Y-m-d H:i'),
                        'duration_days' => $allocation->getDurationInDays(),
                    ];
                }),
            ];

            return response()->json($report);

        } catch (Exception $e) {
            Log::error('Error generating allocation report: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to generate report.'
            ], 500);
        }
    }

    /**
     * Get available data for allocation form
     */
    public function availableData(): JsonResponse
    {
        try {
            $data = [
                'available_rooms' => Room::all()
                    ->filter(fn($room) => $room->isAvailable())
                    ->map(function ($room) {
                        return [
                            'id' => $room->id,
                            'room_code' => $room->room_code,
                            'capacity' => $room->capacity,
                            'available_slots' => $room->getAvailableSlots(),
                            'display_text' => "{$room->room_code} (Available: {$room->getAvailableSlots()}/{$room->capacity})"
                        ];
                    })
                    ->values(),

                'available_employees' => Employee::active()
                    ->whereDoesntHave('activeAllocation')
                    ->orderBy('name')
                    ->get()
                    ->map(function ($employee) {
                        return [
                            'id' => $employee->id,
                            'name' => $employee->name,
                            'employee_code' => $employee->employee_code,
                            'department' => $employee->department,
                            'display_text' => "{$employee->name} ({$employee->employee_code}) - {$employee->department}"
                        ];
                    }),

                'available_guests' => Guest::current()
                    ->whereDoesntHave('activeAllocation')
                    ->orderBy('name')
                    ->get()
                    ->map(function ($guest) {
                        return [
                            'id' => $guest->id,
                            'name' => $guest->name,
                            'company' => $guest->company,
                            'visit_date' => $guest->visit_date->format('Y-m-d'),
                            'display_text' => "{$guest->name}" . ($guest->company ? " - {$guest->company}" : "")
                        ];
                    }),
            ];

            return response()->json($data);

        } catch (Exception $e) {
            Log::error('Error fetching available data: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to fetch available data.'
            ], 500);
        }
    }
}