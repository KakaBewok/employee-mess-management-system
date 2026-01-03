<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Room Management
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div id="roomGrid"></div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 mt-6">
                <h3 class="text-lg font-semibold mb-4">Room Capacity Summary (PivotGrid)</h3>
                <div id="roomPivotGrid"></div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            $(function() {
                // Room DataGrid
                $("#roomGrid").dxDataGrid({
                    dataSource: {
                        load: function() {
                            return $.ajax({
                                url: "{{ route('rooms.data') }}",
                                type: "GET",
                                headers: {
                                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                                }
                            });
                        },
                        insert: function(values) {
                            return $.ajax({
                                url: "{{ route('rooms.store') }}",
                                type: "POST",
                                data: values,
                                headers: {
                                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                                }
                            });
                        },
                        update: function(key, values) {
                            return $.ajax({
                                url: "/rooms/" + key.id,
                                type: "PUT",
                                data: values,
                                headers: {
                                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                                }
                            });
                        },
                        remove: function(key) {
                            return $.ajax({
                                url: "/rooms/" + key.id,
                                type: "DELETE",
                                headers: {
                                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                                }
                            });
                        }
                    },
                    columns: [{
                            dataField: "room_code",
                            caption: "Room Code",
                            validationRules: [{
                                type: "required"
                            }]
                        },
                        {
                            dataField: "capacity",
                            caption: "Capacity",
                            dataType: "number",
                            lookup: {
                                dataSource: [{
                                        value: 1,
                                        text: "1 Person"
                                    },
                                    {
                                        value: 2,
                                        text: "2 Persons"
                                    }
                                ],
                                valueExpr: "value",
                                displayExpr: "text"
                            },
                            validationRules: [{
                                type: "required"
                            }]
                        },
                        {
                            dataField: "status",
                            caption: "Status",
                            lookup: {
                                dataSource: [{
                                        value: "empty",
                                        text: "Empty"
                                    },
                                    {
                                        value: "occupied",
                                        text: "Occupied"
                                    }
                                ],
                                valueExpr: "value",
                                displayExpr: "text"
                            },
                            allowEditing: false
                        },
                        {
                            dataField: "current_occupancy",
                            caption: "Current Occupancy",
                            dataType: "number",
                            allowEditing: false
                        },
                        {
                            dataField: "available_slots",
                            caption: "Available Slots",
                            dataType: "number",
                            allowEditing: false
                        }
                    ],
                    editing: {
                        mode: "popup",
                        allowAdding: true,
                        allowUpdating: true,
                        allowDeleting: true,
                        popup: {
                            title: "Room Information",
                            showTitle: true,
                            width: 400,
                            height: 300
                        },
                        form: {
                            items: ["room_code", "capacity"]
                        }
                    },
                    paging: {
                        pageSize: 15
                    },
                    pager: {
                        showPageSizeSelector: true,
                        allowedPageSizes: [15, 30, 50],
                        showInfo: true
                    },
                    filterRow: {
                        visible: true
                    },
                    searchPanel: {
                        visible: true,
                        width: 240,
                        placeholder: "Search..."
                    },
                    headerFilter: {
                        visible: true
                    }
                });

                // Room PivotGrid
                $.ajax({
                    url: "{{ route('rooms.pivot') }}",
                    type: "GET",
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(data) {
                        $("#roomPivotGrid").dxPivotGrid({
                            dataSource: {
                                fields: [{
                                        caption: "Room Code",
                                        dataField: "room_code",
                                        area: "row"
                                    },
                                    {
                                        caption: "Capacity",
                                        dataField: "capacity",
                                        area: "column"
                                    },
                                    {
                                        caption: "Occupancy",
                                        dataField: "occupancy",
                                        dataType: "number",
                                        summaryType: "sum",
                                        area: "data"
                                    },
                                    {
                                        caption: "Available",
                                        dataField: "available",
                                        dataType: "number",
                                        summaryType: "sum",
                                        area: "data"
                                    }
                                ],
                                store: data
                            },
                            allowSortingBySummary: true,
                            allowFiltering: true,
                            showBorders: true,
                            showColumnGrandTotals: true,
                            showRowGrandTotals: true,
                            showRowTotals: true,
                            showColumnTotals: true
                        });
                    }
                });
            });
        </script>
    @endpush
</x-app-layout>
