{{-- <x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    {{ __("You're logged in!") }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout> --}}

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="text-sm text-gray-600">Total Employees</div>
                        <div class="text-3xl font-bold">{{ $stats['total_employees'] }}</div>
                        <div class="text-xs text-gray-500 mt-2">Active: {{ $stats['active_employees'] }}</div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="text-sm text-gray-600">Total Rooms</div>
                        <div class="text-3xl font-bold">{{ $stats['total_rooms'] }}</div>
                        <div class="text-xs text-gray-500 mt-2">Occupied: {{ $stats['occupied_rooms'] }}</div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="text-sm text-gray-600">Active Allocations</div>
                        <div class="text-3xl font-bold">{{ $stats['active_allocations'] }}</div>
                        <div class="text-xs text-gray-500 mt-2">Guests: {{ $stats['total_guests'] }}</div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-semibold mb-4">Quick Links</h3>
                    <div class="space-y-2">
                        <a href="{{ route('employees.index') }}" class="block p-3 bg-blue-50 hover:bg-blue-100 rounded">
                            Manage Employees
                        </a>
                        <a href="{{ route('rooms.index') }}" class="block p-3 bg-green-50 hover:bg-green-100 rounded">
                            Manage Rooms
                        </a>
                        <a href="{{ route('allocations.index') }}"
                            class="block p-3 bg-purple-50 hover:bg-purple-100 rounded">
                            Room Allocations
                        </a>
                        <a href="{{ route('guests.index') }}"
                            class="block p-3 bg-yellow-50 hover:bg-yellow-100 rounded">
                            Manage Guests
                        </a>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-semibold mb-4">System Information</h3>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span>Room Capacity 1:</span>
                            <span class="font-semibold">20 rooms</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Room Capacity 2:</span>
                            <span class="font-semibold">25 rooms</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Avg. Guests/Week:</span>
                            <span class="font-semibold">4 persons</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
