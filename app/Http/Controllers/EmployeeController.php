<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Http\Requests\EmployeeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class EmployeeController extends Controller
{
    /**
     * Display the employee management page
     */
    public function index()
    {
        try {
            $departments = Employee::DEPARTMENTS;
            $statuses = Employee::STATUSES;
            
            return view('employees.index', compact('departments', 'statuses'));
        } catch (Exception $e) {
            Log::error('Error loading employee index: ' . $e->getMessage());
            return back()->with('error', 'Failed to load employee page.');
        }
    }

    /**
     * Get all employees data for DataGrid
     */
    public function data(): JsonResponse
    {
        try {
            $employees = Employee::orderBy('created_at', 'desc')->get();
            
            return response()->json($employees);
        } catch (Exception $e) {
            Log::error('Error fetching employee data: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to fetch employee data.'
            ], 500);
        }
    }

    /**
     * Store a new employee
     */
    public function store(EmployeeRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $employee = Employee::create($request->validated());

            DB::commit();

            Log::info('Employee created', [
                'employee_id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'message' => 'Employee created successfully.',
                'data' => $employee
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error creating employee: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'Failed to create employee. Please try again.'
            ], 500);
        }
    }

    /**
     * Display the specified employee
     */
    public function show(Employee $employee): JsonResponse
    {
        try {
            $employee->load([
                'activeAllocation.room',
                'historicalAllocations.room'
            ]);

            return response()->json([
                'data' => $employee,
                'has_active_allocation' => $employee->hasActiveAllocation(),
                'current_room' => $employee->getCurrentRoom(),
            ]);

        } catch (Exception $e) {
            Log::error('Error fetching employee: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to fetch employee details.'
            ], 500);
        }
    }

    /**
     * Update the specified employee
     */
    public function update(EmployeeRequest $request, Employee $employee): JsonResponse
    {
        try {
            DB::beginTransaction();

            $employee->update($request->validated());

            DB::commit();

            Log::info('Employee updated', [
                'employee_id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'message' => 'Employee updated successfully.',
                'data' => $employee
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error updating employee: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'Failed to update employee. Please try again.'
            ], 500);
        }
    }

    /**
     * Remove the specified employee
     */
    public function destroy(Employee $employee): JsonResponse
    {
        try {
            // Check if employee has active allocation
            if ($employee->hasActiveAllocation()) {
                return response()->json([
                    'error' => 'Cannot delete employee with active room allocation. Please release the allocation first.'
                ], 422);
            }

            DB::beginTransaction();

            $employeeCode = $employee->employee_code;
            $employee->delete();

            DB::commit();

            Log::info('Employee deleted', [
                'employee_code' => $employeeCode,
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'message' => 'Employee deleted successfully.'
            ], 200);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error deleting employee: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'Failed to delete employee. Please try again.'
            ], 500);
        }
    }

    /**
     * Get employee status chart data
     */
    public function statusChart(): JsonResponse
    {
        try {
            $stats = Employee::selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->get()
                ->map(function ($item) {
                    return [
                        'status' => ucfirst($item->status),
                        'count' => $item->count
                    ];
                });

            return response()->json($stats);

        } catch (Exception $e) {
            Log::error('Error fetching status chart: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to fetch chart data.'
            ], 500);
        }
    }

    /**
     * Get department statistics
     */
    public function departmentStats(): JsonResponse
    {
        try {
            $stats = Employee::selectRaw('department, status, COUNT(*) as count')
                ->groupBy('department', 'status')
                ->get()
                ->groupBy('department')
                ->map(function ($group) {
                    return [
                        'total' => $group->sum('count'),
                        'active' => $group->where('status', 'active')->sum('count'),
                        'inactive' => $group->where('status', 'inactive')->sum('count'),
                    ];
                });

            return response()->json($stats);

        } catch (Exception $e) {
            Log::error('Error fetching department stats: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to fetch department statistics.'
            ], 500);
        }
    }

    /**
     * Get active employees without allocation
     */
    public function availableForAllocation(): JsonResponse
    {
        try {
            $employees = Employee::active()
                ->whereDoesntHave('activeAllocation')
                ->orderBy('name')
                ->get();

            return response()->json($employees);

        } catch (Exception $e) {
            Log::error('Error fetching available employees: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to fetch available employees.'
            ], 500);
        }
    }
}
