<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Guest Management
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div id="guestGrid"></div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        $(function() {
            $("#guestGrid").dxDataGrid({
                dataSource: {
                    load: function() {
                        return $.ajax({
                            url: "{{ route('guests.data') }}",
                            type: "GET",
                            headers: {
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                            }
                        });
                    },
                    insert: function(values) {
                        return $.ajax({
                            url: "{{ route('guests.store') }}",
                            type: "POST",
                            data: values,
                            headers: {
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                            }
                        });
                    },
                    remove: function(key) {
                        return $.ajax({
                            url: "/guests/" + key.id,
                            type: "DELETE",
                            headers: {
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                            }
                        });
                    }
                },
                columns: [
                    {
                        dataField: "name",
                        caption: "Guest Name",
                        validationRules: [{ type: "required" }]
                    },
                    {
                        dataField: "visit_date",
                        caption: "Visit Date",
                        dataType: "date",
                        validationRules: [{ type: "required" }]
                    }
                ],
                editing: {
                    mode: "popup",
                    allowAdding: true,
                    allowDeleting: true,
                    popup: {
                        title: "Guest Information",
                        showTitle: true,
                        width: 400,
                        height: 250
                    },
                    form: {
                        items: ["name", "visit_date"]
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
                }
            });
        });
    </script>
    @endpush
</x-app-layout>