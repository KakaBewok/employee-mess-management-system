<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Guest;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
   public function run(): void
    {
        // Create Admin User
        User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);

        // Seed Employees (60 active, 12 inactive)
        $departments = ['HR', 'Finance', 'Produksi', 'Sarana', 'Safety'];
        
        // Active Employees
        for ($i = 1; $i <= 60; $i++) {
            Employee::create([
                'employee_code' => 'EMP' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'name' => 'Employee ' . $i,
                'department' => $departments[array_rand($departments)],
                'status' => 'active',
            ]);
        }

        // Inactive Employees
        for ($i = 61; $i <= 72; $i++) {
            Employee::create([
                'employee_code' => 'EMP' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'name' => 'Employee ' . $i,
                'department' => $departments[array_rand($departments)],
                'status' => 'inactive',
            ]);
        }

        // Seed Rooms (20 with capacity 1, 25 with capacity 2)
        // Capacity 1
        for ($i = 1; $i <= 20; $i++) {
            Room::create([
                'room_code' => 'R1-' . str_pad($i, 2, '0', STR_PAD_LEFT),
                'capacity' => 1,
                'status' => 'empty',
            ]);
        }

        // Capacity 2
        for ($i = 1; $i <= 25; $i++) {
            Room::create([
                'room_code' => 'R2-' . str_pad($i, 2, '0', STR_PAD_LEFT),
                'capacity' => 2,
                'status' => 'empty',
            ]);
        }

        // Seed Guests (average 4 per week, let's create 8 for 2 weeks)
        $today = now();
        for ($i = 1; $i <= 8; $i++) {
            Guest::create([
                'name' => 'Guest ' . $i,
                'visit_date' => $today->copy()->addDays(rand(-7, 7)),
            ]);
        }

        $this->command->info('Database seeded successfully!');
        $this->command->info('Admin credentials: admin@example.com / password');
    }
}
