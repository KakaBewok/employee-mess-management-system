<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Guest;
use App\Models\Room;
use App\Models\RoomAllocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class DashboardController extends Controller
{
    /**
     * Display the dashboard
     */
    public function index()
    {
        try {
            // Get statistics with error handling for each section
            $stats = $this->getStatistics();
            $recentActivities = $this->getRecentActivities();
            $occupancyTrend = $this->getOccupancyTrend();

            return view('dashboard', compact('stats', 'recentActivities', 'occupancyTrend'));

        } catch (Exception $e) {
            Log::error('Error loading dashboard: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            // Return view with empty data if error occurs
            return view('dashboard', [
                'stats' => $this->getEmptyStats(),
                'recentActivities' => [],
                'occupancyTrend' => []
            ])->with('error', 'Some dashboard data could not be loaded. Error: ' . $e->getMessage());
        }
    }

    /**
     * Get dashboard statistics
     */
    private function getStatistics(): array
    {
        try {
            // Employee Statistics
            $totalEmployees = Employee::count();
            $activeEmployees = Employee::where('status', 'active')->count();
            $inactiveEmployees = Employee::where('status', 'inactive')->count();
            $employeesWithAllocation = Employee::whereHas('allocations', function($query) {
                $query->whereNull('released_at');
            })->count();

            // Room Statistics
            $totalRooms = Room::count();
            $occupiedRooms = Room::where('status', 'occupied')->count();
            $emptyRooms = Room::where('status', 'empty')->count();
            $totalCapacity = Room::sum('capacity');
            $capacity1Rooms = Room::where('capacity', 1)->count();
            $capacity2Rooms = Room::where('capacity', 2)->count();

            // Guest Statistics
            $totalGuests = Guest::count();
            $currentGuests = Guest::where('visit_date', '<=', now())
                ->where(function($q) {
                    $q->whereNull('checkout_date')
                      ->orWhere('checkout_date', '>=', now());
                })
                ->count();
            $guestsWithAllocation = Guest::whereHas('allocations', function($query) {
                $query->whereNull('released_at');
            })->count();

            // Allocation Statistics
            $activeAllocations = RoomAllocation::whereNull('released_at')->count();
            $employeeAllocations = RoomAllocation::whereNull('released_at')
                ->whereNotNull('employee_id')
                ->count();
            $guestAllocations = RoomAllocation::whereNull('released_at')
                ->whereNotNull('guest_id')
                ->count();
            $historicalAllocations = RoomAllocation::whereNotNull('released_at')->count();

            // Calculate rates
            $occupancyRate = $totalCapacity > 0 
                ? round(($activeAllocations / $totalCapacity) * 100, 1) 
                : 0;
            $roomOccupancyRate = $totalRooms > 0 
                ? round(($occupiedRooms / $totalRooms) * 100, 1) 
                : 0;
            $employeeAllocationRate = $activeEmployees > 0 
                ? round(($employeesWithAllocation / $activeEmployees) * 100, 1) 
                : 0;

            return [
                // Employee Stats
                'total_employees' => $totalEmployees,
                'active_employees' => $activeEmployees,
                'inactive_employees' => $inactiveEmployees,
                'employees_with_allocation' => $employeesWithAllocation,
                'employee_allocation_rate' => $employeeAllocationRate,

                // Room Stats
                'total_rooms' => $totalRooms,
                'occupied_rooms' => $occupiedRooms,
                'empty_rooms' => $emptyRooms,
                'total_capacity' => $totalCapacity,
                'capacity_1_rooms' => $capacity1Rooms,
                'capacity_2_rooms' => $capacity2Rooms,
                'room_occupancy_rate' => $roomOccupancyRate,

                // Guest Stats
                'total_guests' => $totalGuests,
                'current_guests' => $currentGuests,
                'guests_with_allocation' => $guestsWithAllocation,

                // Allocation Stats
                'active_allocations' => $activeAllocations,
                'employee_allocations' => $employeeAllocations,
                'guest_allocations' => $guestAllocations,
                'historical_allocations' => $historicalAllocations,
                'available_slots' => $totalCapacity - $activeAllocations,
                'occupancy_rate' => $occupancyRate,

                // Department Breakdown
                'department_stats' => $this->getDepartmentStats(),
            ];
        } catch (Exception $e) {
            Log::error('Error in getStatistics: ' . $e->getMessage());
            return $this->getEmptyStats();
        }
    }

    /**
     * Get empty stats (fallback)
     */
    private function getEmptyStats(): array
    {
        return [
            'total_employees' => 0,
            'active_employees' => 0,
            'inactive_employees' => 0,
            'employees_with_allocation' => 0,
            'employee_allocation_rate' => 0,
            'total_rooms' => 0,
            'occupied_rooms' => 0,
            'empty_rooms' => 0,
            'total_capacity' => 0,
            'capacity_1_rooms' => 0,
            'capacity_2_rooms' => 0,
            'room_occupancy_rate' => 0,
            'total_guests' => 0,
            'current_guests' => 0,
            'guests_with_allocation' => 0,
            'active_allocations' => 0,
            'employee_allocations' => 0,
            'guest_allocations' => 0,
            'historical_allocations' => 0,
            'available_slots' => 0,
            'occupancy_rate' => 0,
            'department_stats' => [],
        ];
    }

    /**
     * Get department statistics
     */
    private function getDepartmentStats(): array
    {
        try {
            return Employee::selectRaw('department, status, COUNT(*) as count')
                ->groupBy('department', 'status')
                ->get()
                ->groupBy('department')
                ->map(function ($group, $department) {
                    $active = $group->where('status', 'active')->sum('count');
                    $inactive = $group->where('status', 'inactive')->sum('count');
                    $total = $active + $inactive;

                    $withAllocation = Employee::where('department', $department)
                        ->whereHas('allocations', function($query) {
                            $query->whereNull('released_at');
                        })
                        ->count();

                    return [
                        'department' => $department,
                        'total' => $total,
                        'active' => $active,
                        'inactive' => $inactive,
                        'with_allocation' => $withAllocation,
                    ];
                })
                ->values()
                ->toArray();
        } catch (Exception $e) {
            Log::error('Error in getDepartmentStats: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get recent activities
     */
    private function getRecentActivities(int $limit = 10): array
    {
        try {
            $recentAllocations = RoomAllocation::with(['room', 'employee', 'guest'])
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($allocation) {
                    $isActive = is_null($allocation->released_at);
                    
                    $occupantName = 'Unknown';
                    if ($allocation->employee) {
                        $occupantName = $allocation->employee->name;
                    } elseif ($allocation->guest) {
                        $occupantName = $allocation->guest->name;
                    }

                    return [
                        'type' => $isActive ? 'allocation' : 'release',
                        'message' => $isActive 
                            ? "Room {$allocation->room->room_code} allocated to {$occupantName}"
                            : "Room {$allocation->room->room_code} released by {$occupantName}",
                        'time' => $allocation->created_at->diffForHumans(),
                        'timestamp' => $allocation->created_at->format('Y-m-d H:i:s'),
                    ];
                })
                ->toArray();

            return $recentAllocations;
        } catch (Exception $e) {
            Log::error('Error in getRecentActivities: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get occupancy trend for the last 7 days
     */
    private function getOccupancyTrend(): array
    {
        try {
            $trend = [];
            $totalCapacity = Room::sum('capacity');
            
            for ($i = 6; $i >= 0; $i--) {
                $date = now()->subDays($i)->startOfDay();
                
                $allocations = RoomAllocation::where('allocated_at', '<=', $date->endOfDay())
                    ->where(function ($query) use ($date) {
                        $query->whereNull('released_at')
                            ->orWhere('released_at', '>', $date->endOfDay());
                    })
                    ->count();

                $trend[] = [
                    'date' => $date->format('Y-m-d'),
                    'date_formatted' => $date->format('M d'),
                    'occupancy' => $allocations,
                    'occupancy_rate' => $totalCapacity > 0 
                        ? round(($allocations / $totalCapacity) * 100, 1) 
                        : 0,
                ];
            }

            return $trend;
        } catch (Exception $e) {
            Log::error('Error in getOccupancyTrend: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get dashboard data as JSON (for AJAX refresh)
     */
    public function data()
    {
        try {
            $data = [
                'stats' => $this->getStatistics(),
                'recent_activities' => $this->getRecentActivities(),
                'occupancy_trend' => $this->getOccupancyTrend(),
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (Exception $e) {
            Log::error('Error fetching dashboard data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch dashboard data.',
                'message' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }
}