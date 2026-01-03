<?php

namespace App\Http\Controllers;

use App\Http\Requests\GuestRequest;
use App\Models\Guest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class GuestController extends Controller
{
    /**
     * Display the guest management page
     */
    public function index()
    {
        try {
            return view('guests.index');
        } catch (Exception $e) {
            Log::error('Error loading guest index: ' . $e->getMessage());
            return back()->with('error', 'Failed to load guest page.');
        }
    }

    /**
     * Get all guests data for DataGrid
     */
    public function data(): JsonResponse
    {
        try {
            $guests = Guest::orderBy('visit_date', 'desc')
                ->get()
                ->map(function ($guest) {
                    return [
                        'id' => $guest->id,
                        'name' => $guest->name,
                        'phone' => $guest->phone,
                        'company' => $guest->company,
                        'visit_date' => $guest->visit_date->format('Y-m-d'),
                        'checkout_date' => $guest->checkout_date?->format('Y-m-d'),
                        'visit_duration' => $guest->getVisitDuration(),
                        'is_current' => $guest->isCurrentlyVisiting(),
                        'has_active_allocation' => $guest->hasActiveAllocation(),
                        'notes' => $guest->notes,
                        'created_at' => $guest->created_at->format('Y-m-d H:i:s'),
                    ];
                });

            return response()->json($guests);

        } catch (Exception $e) {
            Log::error('Error fetching guest data: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to fetch guest data.'
            ], 500);
        }
    }

    /**
     * Store a new guest
     */
    public function store(GuestRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $guest = Guest::create($request->validated());

            DB::commit();

            Log::info('Guest created', [
                'guest_id' => $guest->id,
                'guest_name' => $guest->name,
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'message' => 'Guest created successfully.',
                'data' => $guest
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error creating guest: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'Failed to create guest. Please try again.'
            ], 500);
        }
    }

    /**
     * Display the specified guest
     */
    public function show(Guest $guest): JsonResponse
    {
        try {
            $guest->load([
                'activeAllocation.room',
                'historicalAllocations.room'
            ]);

            return response()->json([
                'data' => $guest,
                'has_active_allocation' => $guest->hasActiveAllocation(),
                'current_room' => $guest->getCurrentRoom(),
                'is_currently_visiting' => $guest->isCurrentlyVisiting(),
                'visit_duration' => $guest->getVisitDuration(),
            ]);

        } catch (Exception $e) {
            Log::error('Error fetching guest: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to fetch guest details.'
            ], 500);
        }
    }

    /**
     * Update the specified guest
     */
    public function update(GuestRequest $request, Guest $guest): JsonResponse
    {
        try {
            DB::beginTransaction();

            $guest->update($request->validated());

            DB::commit();

            Log::info('Guest updated', [
                'guest_id' => $guest->id,
                'guest_name' => $guest->name,
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'message' => 'Guest updated successfully.',
                'data' => $guest
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error updating guest: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'Failed to update guest. Please try again.'
            ], 500);
        }
    }

    /**
     * Remove the specified guest
     */
    public function destroy(Guest $guest): JsonResponse
    {
        try {
            // Check if guest has active allocation
            if ($guest->hasActiveAllocation()) {
                return response()->json([
                    'error' => 'Cannot delete guest with active room allocation. Please release the allocation first.'
                ], 422);
            }

            DB::beginTransaction();

            $guestName = $guest->name;
            $guest->delete();

            DB::commit();

            Log::info('Guest deleted', [
                'guest_name' => $guestName,
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'message' => 'Guest deleted successfully.'
            ], 200);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error deleting guest: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'Failed to delete guest. Please try again.'
            ], 500);
        }
    }

    /**
     * Get current guests
     */
    public function currentGuests(): JsonResponse
    {
        try {
            $guests = Guest::current()
                ->orderBy('visit_date')
                ->get();

            return response()->json($guests);

        } catch (Exception $e) {
            Log::error('Error fetching current guests: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to fetch current guests.'
            ], 500);
        }
    }

    /**
     * Get guests available for allocation
     */
    public function availableForAllocation(): JsonResponse
    {
        try {
            $guests = Guest::current()
                ->whereDoesntHave('activeAllocation')
                ->orderBy('name')
                ->get();

            return response()->json($guests);

        } catch (Exception $e) {
            Log::error('Error fetching available guests: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to fetch available guests.'
            ], 500);
        }
    }

    /**
     * Get guest statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = [
                'total_guests' => Guest::count(),
                'current_guests' => Guest::current()->count(),
                'upcoming_guests' => Guest::upcoming()->count(),
                'past_guests' => Guest::past()->count(),
                'guests_with_allocation' => Guest::has('activeAllocation')->count(),
            ];

            return response()->json($stats);

        } catch (Exception $e) {
            Log::error('Error fetching guest statistics: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to fetch statistics.'
            ], 500);
        }
    }
}
