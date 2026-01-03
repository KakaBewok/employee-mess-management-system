<?php

use App\Http\Controllers\AllocationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\GuestController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoomController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Route::get('/dashboard', function () {
//     return view('dashboard');
// })->middleware(['auth', 'verified'])->name('dashboard');

// Route::middleware('auth')->group(function () {
//     Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
//     Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
//     Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
// });

//

Route::middleware('auth')->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Employees
    Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::get('/employees/data', [EmployeeController::class, 'data'])->name('employees.data');
    Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
    Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
    Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy'])->name('employees.destroy');
    Route::get('/employees/status-chart', [EmployeeController::class, 'statusChart'])->name('employees.chart');

    // Rooms
    Route::get('/rooms', [RoomController::class, 'index'])->name('rooms.index');
    Route::get('/rooms/data', [RoomController::class, 'data'])->name('rooms.data');
    Route::post('/rooms', [RoomController::class, 'store'])->name('rooms.store');
    Route::put('/rooms/{room}', [RoomController::class, 'update'])->name('rooms.update');
    Route::delete('/rooms/{room}', [RoomController::class, 'destroy'])->name('rooms.destroy');
    Route::get('/rooms/pivot-data', [RoomController::class, 'pivotData'])->name('rooms.pivot');

    // Guests
    Route::get('/guests', [GuestController::class, 'index'])->name('guests.index');
    Route::get('/guests/data', [GuestController::class, 'data'])->name('guests.data');
    Route::post('/guests', [GuestController::class, 'store'])->name('guests.store');
    Route::delete('/guests/{guest}', [GuestController::class, 'destroy'])->name('guests.destroy');

    // Allocations
    Route::get('/allocations', [AllocationController::class, 'index'])->name('allocations.index');
    Route::get('/allocations/data', [AllocationController::class, 'data'])->name('allocations.data');
    Route::post('/allocations', [AllocationController::class, 'store'])->name('allocations.store');
    Route::post('/allocations/{allocation}/release', [AllocationController::class, 'release'])->name('allocations.release');
});

require __DIR__.'/auth.php';
