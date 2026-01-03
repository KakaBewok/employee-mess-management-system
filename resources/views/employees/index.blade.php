<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Employee Management
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div id="employeeGrid"></div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 mt-6">
                <h3 class="text-lg font-semibold mb-4">Employee Status Chart</h3>
                <div id="employeeChart" style="height: 400px;"></div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            $(function() {
                // Employee DataGrid
                $("#employeeGrid").dxDataGrid({
                    dataSource: {
                        load: function() {
                            return $.ajax({
                                url: "{{ route('employees.data') }}",
                                type: "GET",
                                headers: {
                                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                                }
                            });
                        },
                        insert: function(values) {
                            return $.ajax({
                                url: "{{ route('employees.store') }}",
                                type: "POST",
                                data: values,
                                headers: {
                                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                                }
                            });
                        },
                        update: function(key, values) {
                            return $.ajax({
                                url: "/employees/" + key.id,
                                type: "PUT",
                                data: values,
                                headers: {
                                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                                }
                            });
                        },
                        remove: function(key) {
                            return $.ajax({
                                url: "/employees/" + key.id,
                                type: "DELETE",
                                headers: {
                                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                                }
                            });
                        }
                    },
                    columns: [{
                            dataField: "employee_code",
                            caption: "Employee Code",
                            validationRules: [{
                                type: "required"
                            }]
                        },
                        {
                            dataField: "name",
                            caption: "Name",
                            validationRules: [{
                                    type: "required"
                                },
                                {
                                    type: "pattern",
                                    pattern: /^[A-Za-z\s]+$/,
                                    message: "Name must contain only letters (A-Z)"
                                }
                            ]
                        },
                        {
                            dataField: "department",
                            caption: "Department",
                            lookup: {
                                dataSource: [{
                                        value: "HR",
                                        text: "HR"
                                    },
                                    {
                                        value: "Finance",
                                        text: "Finance"
                                    },
                                    {
                                        value: "Produksi",
                                        text: "Produksi"
                                    },
                                    {
                                        value: "Sarana",
                                        text: "Sarana"
                                    },
                                    {
                                        value: "Safety",
                                        text: "Safety"
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
                                        value: "active",
                                        text: "Active"
                                    },
                                    {
                                        value: "inactive",
                                        text: "Inactive"
                                    }
                                ],
                                valueExpr: "value",
                                displayExpr: "text"
                            },
                            validationRules: [{
                                type: "required"
                            }]
                        }
                    ],
                    editing: {
                        mode: "popup",
                        allowAdding: true,
                        allowUpdating: true,
                        allowDeleting: true,
                        popup: {
                            title: "Employee Information",
                            showTitle: true,
                            width: 500,
                            height: 400
                        },
                        form: {
                            items: ["name", "department", "status"]
                        }
                    },
                    paging: {
                        pageSize: 10
                    },
                    pager: {
                        showPageSizeSelector: true,
                        allowedPageSizes: [10, 20, 50],
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
                    },
                    export: {
                        enabled: true
                    },
                    onInitNewRow: function(e) {
                        e.data.status = "active";
                    }
                });

                // Employee Status Chart
                $.ajax({
                    url: "{{ route('employees.chart') }}",
                    type: "GET",
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(data) {
                        $("#employeeChart").dxChart({
                            dataSource: data,
                            commonSeriesSettings: {
                                argumentField: "status",
                                type: "bar"
                            },
                            series: [{
                                valueField: "count",
                                name: "Employee Count"
                            }],
                            legend: {
                                verticalAlignment: "bottom",
                                horizontalAlignment: "center"
                            },
                            title: {
                                text: "Employee Status Distribution"
                            },
                            argumentAxis: {
                                label: {
                                    customizeText: function() {
                                        return this.value.charAt(0).toUpperCase() + this.value
                                            .slice(1);
                                    }
                                }
                            },
                            tooltip: {
                                enabled: true,
                                customizeTooltip: function(arg) {
                                    return {
                                        text: arg.valueText + " employees"
                                    };
                                }
                            }
                        });
                    }
                });
            });
        </script>
    @endpush
</x-app-layout>
