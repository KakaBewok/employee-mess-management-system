<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Room Allocation Management
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 mb-6">
                <h3 class="text-lg font-semibold mb-4">Create New Allocation</h3>
                <form id="allocationForm">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Room</label>
                            <select name="room_id" id="room_id" class="w-full border-gray-300 rounded-md shadow-sm"
                                required>
                                <option value="">Select Room</option>
                                @foreach ($rooms as $room)
                                    <option value="{{ $room['id'] }}">
                                        {{ $room['display_text'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Employee (Optional)</label>
                            <select name="employee_id" id="employee_id"
                                class="w-full border-gray-300 rounded-md shadow-sm">
                                <option value="">Select Employee</option>
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee['id'] }}">
                                        {{ $employee['display_text'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Guest (Optional)</label>
                            <select name="guest_id" id="guest_id" class="w-full border-gray-300 rounded-md shadow-sm">
                                <option value="">Select Guest</option>
                                @foreach ($guests as $guest)
                                    <option value="{{ $guest['id'] }}">
                                        {{ $guest['display_text'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="mt-4">
                        <button type="submit"
                            class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                            Allocate Room
                        </button>
                    </div>
                    <div id="allocationMessage" class="mt-4"></div>
                </form>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">Active Allocations</h3>
                <div id="allocationGrid"></div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            $(function() {
                var allocationGrid;

                // Allocation DataGrid
                allocationGrid = $("#allocationGrid").dxDataGrid({
                    dataSource: {
                        load: function() {
                            return $.ajax({
                                url: "{{ route('allocations.data') }}",
                                type: "GET",
                                headers: {
                                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                                }
                            });
                        }
                    },
                    columns: [{
                            dataField: "room_code",
                            caption: "Room Code"
                        },
                        {
                            dataField: "occupant_name",
                            caption: "Occupant Name"
                        },
                        {
                            dataField: "occupant_type",
                            caption: "Type"
                        },
                        {
                            dataField: "allocated_at",
                            caption: "Allocated At",
                            dataType: "datetime"
                        },
                        {
                            caption: "Actions",
                            type: "buttons",
                            buttons: [{
                                text: "Release",
                                cssClass: "bg-red-500 text-white",
                                onClick: function(e) {
                                    if (confirm(
                                            "Are you sure you want to release this allocation?"
                                        )) {
                                        $.ajax({
                                            url: "/allocations/" + e.row.data.id +
                                                "/release",
                                            type: "POST",
                                            headers: {
                                                'X-CSRF-TOKEN': $(
                                                    'meta[name="csrf-token"]').attr(
                                                    'content')
                                            },
                                            success: function(response) {
                                                allocationGrid.getDataSource()
                                                    .reload();
                                                showMessage(
                                                    "Allocation released successfully!",
                                                    "success");
                                            },
                                            error: function(xhr) {
                                                showMessage("Error: " + xhr
                                                    .responseJSON.error, "error"
                                                );
                                            }
                                        });
                                    }
                                }
                            }]
                        }
                    ],
                    paging: {
                        pageSize: 10
                    },
                    filterRow: {
                        visible: true
                    },
                    searchPanel: {
                        visible: true,
                        width: 240,
                        placeholder: "Search..."
                    }
                }).dxDataGrid("instance");

                // Allocation Form Submit
                $("#allocationForm").on("submit", function(e) {
                    e.preventDefault();

                    var formData = {
                        room_id: $("#room_id").val(),
                        employee_id: $("#employee_id").val() || null,
                        guest_id: $("#guest_id").val() || null
                    };

                    $.ajax({
                        url: "{{ route('allocations.store') }}",
                        type: "POST",
                        data: formData,
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        success: function(response) {
                            allocationGrid.refresh();
                            $("#allocationForm")[0].reset();
                            showMessage("Room allocated successfully!", "success");
                        },
                        error: function(xhr) {
                            var errorMsg = xhr.responseJSON?.error || "An error occurred";
                            showMessage("Error: " + errorMsg, "error");
                        }
                    });
                });

                // Prevent selecting both employee and guest
                $("#employee_id").on("change", function() {
                    if ($(this).val()) {
                        $("#guest_id").val("").prop("disabled", true);
                    } else {
                        $("#guest_id").prop("disabled", false);
                    }
                });

                $("#guest_id").on("change", function() {
                    if ($(this).val()) {
                        $("#employee_id").val("").prop("disabled", true);
                    } else {
                        $("#employee_id").prop("disabled", false);
                    }
                });

                function showMessage(message, type) {
                    var bgColor = type === "success" ? "bg-green-100 text-green-700" : "bg-red-100 text-red-700";
                    $("#allocationMessage").html(
                        '<div class="' + bgColor + ' border rounded p-3">' + message + '</div>'
                    );
                    setTimeout(function() {
                        $("#allocationMessage").html("");
                    }, 5000);
                }
            });
        </script>
    @endpush
</x-app-layout>
