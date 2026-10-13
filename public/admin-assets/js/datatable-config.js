/**
 * Global DataTables Configuration
 * Applies responsive settings and consistent behavior across all tables
 */

// Set DataTables defaults before any table initialization
if (typeof $.fn.dataTable !== "undefined") {
    $.extend(true, $.fn.dataTable.defaults, {
        responsive: true,
        autoWidth: false,
        scrollX: true,
        scrollCollapse: true,
        language: {
            paginate: {
                previous: '<i class="bi bi-chevron-left"></i>',
                next: '<i class="bi bi-chevron-right"></i>',
                first: '<i class="bi bi-chevron-bar-left"></i>',
                last: '<i class="bi bi-chevron-bar-right"></i>',
            },
            lengthMenu: "Show _MENU_ entries",
            search: "_INPUT_",
            searchPlaceholder: "Search...",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            infoEmpty: "No entries available",
            infoFiltered: "(filtered from _MAX_ total entries)",
            loadingRecords: "Loading...",
            processing:
                '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>',
            zeroRecords:
                '<div class="text-center"><i class="bi bi-inbox fs-1 text-muted"></i><p class="mt-2">No matching records found</p></div>',
            emptyTable:
                '<div class="text-center"><i class="bi bi-inbox fs-1 text-muted"></i><p class="mt-2">No data available</p></div>',
        },
        dom:
            '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
            '<"row"<"col-sm-12"tr>>' +
            '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        pageLength: 25,
        lengthMenu: [
            [10, 25, 50, 100, -1],
            [10, 25, 50, 100, "All"],
        ],
        processing: true,
        stateSave: false, // Can be enabled per table if needed
        order: [[0, "desc"]], // Default sort
    });

    // Add custom styling classes after table draw
    $(document).on("draw.dt", function (e) {
        const table = $(e.target);

        // Add table-responsive wrapper if not exists
        if (
            !table.parent().hasClass("dataTables_scroll") &&
            !table.parent().hasClass("table-responsive")
        ) {
            table.wrap('<div class="table-responsive"></div>');
        }

        // Ensure pagination has proper spacing
        table
            .closest(".dataTables_wrapper")
            .find(".dataTables_paginate")
            .addClass("pagination-sm");
    });

    // Handle window resize for responsive behavior
    let resizeTimer;
    $(window).on("resize", function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
            if (typeof $.fn.dataTable !== "undefined") {
                $.fn.dataTable
                    .tables({ visible: true, api: true })
                    .columns.adjust()
                    .responsive.recalc();
            }
        }, 250);
    });
}

// Initialize when document is ready
$(document).ready(function () {
    // Apply responsive class to all DataTables
    $(".dataTable, #dataTable").addClass("nowrap");

    // Log for debugging
    console.log("DataTables global configuration loaded");
});
