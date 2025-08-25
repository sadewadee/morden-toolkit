/**
 * Performance Monitor Tab Enhancements
 * Filtering and sorting functionality for Images and Hooks tabs
 */

// Universal Sortable Functionality
var MT_Sortable = {
    init: function() {
        this.bindSortableHeaders();
    },

    bindSortableHeaders: function() {
        var sortableHeaders = document.querySelectorAll('.sortable');

        sortableHeaders.forEach(function(header) {
            header.addEventListener('click', function() {
                MT_Sortable.sortTable(this);
            });

            // Add visual indicators
            header.style.cursor = 'pointer';
            header.style.position = 'relative';
        });
    },

    sortTable: function(header) {
        var table = header.closest('table');
        var tbody = table.querySelector('tbody');
        var column = header.getAttribute('data-column');
        var columnIndex = Array.from(header.parentNode.children).indexOf(header);

        if (!tbody || !column) return;

        var rows = Array.from(tbody.querySelectorAll('tr'));
        var isAscending = header.getAttribute('data-sort-direction') !== 'asc';

        // Remove previous sort indicators
        var allHeaders = table.querySelectorAll('.sortable');
        allHeaders.forEach(function(h) {
            h.removeAttribute('data-sort-direction');
            h.classList.remove('sorted-asc', 'sorted-desc');
        });

        // Set new sort direction
        header.setAttribute('data-sort-direction', isAscending ? 'asc' : 'desc');
        header.classList.add(isAscending ? 'sorted-asc' : 'sorted-desc');

        rows.sort(function(a, b) {
            var aCell = a.children[columnIndex];
            var bCell = b.children[columnIndex];

            if (!aCell || !bCell) return 0;

            var aVal = MT_Sortable.getSortValue(aCell, column);
            var bVal = MT_Sortable.getSortValue(bCell, column);

            var result = MT_Sortable.compare(aVal, bVal, column);
            return isAscending ? result : -result;
        });

        // Reorder rows in table
        rows.forEach(function(row) {
            tbody.appendChild(row);
        });

        // Update row numbers if they exist
        MT_Sortable.updateRowNumbers(tbody);
    },

    getSortValue: function(cell, column) {
        var text = cell.textContent.trim();

        // Handle different data types
        switch (column) {
            case 'time':
            case 'loadtime':
            case 'filesize':
            case 'memory':
            case 'order':
            case 'priority':
                // Extract numeric values
                var numMatch = text.match(/([0-9.]+)/);
                return numMatch ? parseFloat(numMatch[1]) : 0;

            case 'version':
                // Handle version sorting (e.g., 1.2.3)
                if (text === 'N/A' || text === '') return '0';
                return text;

            default:
                return text.toLowerCase();
        }
    },

    compare: function(a, b, column) {
        if (typeof a === 'number' && typeof b === 'number') {
            return a - b;
        }

        if (typeof a === 'string' && typeof b === 'string') {
            return a.localeCompare(b);
        }

        return 0;
    },

    updateRowNumbers: function(tbody) {
        var visibleRows = tbody.querySelectorAll('tr:not([style*="display: none"])');
        visibleRows.forEach(function(row, index) {
            var numberCell = row.querySelector('.query-number');
            if (numberCell) {
                numberCell.textContent = index + 1;
            }
        });
    }
};

// Images Tab Functionality
var MT_Images = {
    init: function() {
        this.bindEvents();
        this.populateFilters();
    },

    bindEvents: function() {
        var sourceFilter = document.getElementById('mt-images-source-filter');
        var hostnameFilter = document.getElementById('mt-images-hostname-filter');
        var sortSelect = document.getElementById('mt-images-sort');

        if (sourceFilter) {
            sourceFilter.addEventListener('change', this.filterImages.bind(this));
        }
        if (hostnameFilter) {
            hostnameFilter.addEventListener('change', this.filterImages.bind(this));
        }
        if (sortSelect) {
            sortSelect.addEventListener('change', this.sortImages.bind(this));
        }
    },

    populateFilters: function() {
        var table = document.querySelector('.mt-images-table tbody');
        if (!table) return;

        var sources = new Set();
        var hostnames = new Set();

        var rows = table.querySelectorAll('tr');
        rows.forEach(function(row) {
            var source = row.getAttribute('data-source');
            var hostname = row.getAttribute('data-hostname');

            if (source) sources.add(source);
            if (hostname) hostnames.add(hostname);
        });

        // Populate source filter
        var sourceFilter = document.getElementById('mt-images-source-filter');
        if (sourceFilter) {
            sources.forEach(function(source) {
                var option = document.createElement('option');
                option.value = source;
                option.textContent = source;
                sourceFilter.appendChild(option);
            });
        }

        // Populate hostname filter
        var hostnameFilter = document.getElementById('mt-images-hostname-filter');
        if (hostnameFilter) {
            hostnames.forEach(function(hostname) {
                var option = document.createElement('option');
                option.value = hostname;
                option.textContent = hostname;
                hostnameFilter.appendChild(option);
            });
        }
    },

    filterImages: function() {
        var sourceFilter = document.getElementById('mt-images-source-filter');
        var hostnameFilter = document.getElementById('mt-images-hostname-filter');
        var table = document.querySelector('.mt-images-table tbody');

        if (!table) return;

        var selectedSource = sourceFilter ? sourceFilter.value : '';
        var selectedHostname = hostnameFilter ? hostnameFilter.value : '';

        var rows = table.querySelectorAll('tr');
        var visibleCount = 0;

        rows.forEach(function(row) {
            var source = row.getAttribute('data-source') || '';
            var hostname = row.getAttribute('data-hostname') || '';

            var showRow = true;

            if (selectedSource && source !== selectedSource) {
                showRow = false;
            }

            if (selectedHostname && hostname !== selectedHostname) {
                showRow = false;
            }

            if (showRow) {
                row.style.display = '';
                visibleCount++;
                // Update row number
                var numberCell = row.querySelector('.query-number');
                if (numberCell) {
                    numberCell.textContent = visibleCount;
                }
            } else {
                row.style.display = 'none';
            }
        });
    },

    sortImages: function() {
        var sortSelect = document.getElementById('mt-images-sort');
        var table = document.querySelector('.mt-images-table tbody');

        if (!table || !sortSelect) return;

        var sortBy = sortSelect.value;
        var rows = Array.from(table.querySelectorAll('tr'));

        rows.sort(function(a, b) {
            var aVal, bVal;

            switch (sortBy) {
                case 'size':
                    aVal = parseInt(a.getAttribute('data-size')) || 0;
                    bVal = parseInt(b.getAttribute('data-size')) || 0;
                    return bVal - aVal; // Descending

                case 'load_time':
                    aVal = parseInt(a.getAttribute('data-load-time')) || 0;
                    bVal = parseInt(b.getAttribute('data-load-time')) || 0;
                    return bVal - aVal; // Descending

                case 'source':
                    aVal = a.getAttribute('data-source') || '';
                    bVal = b.getAttribute('data-source') || '';
                    return aVal.localeCompare(bVal);

                default:
                    return 0;
            }
        });

        // Reorder rows in table
        rows.forEach(function(row) {
            table.appendChild(row);
        });

        // Update row numbers
        this.updateRowNumbers();
    },

    updateRowNumbers: function() {
        var table = document.querySelector('.mt-images-table tbody');
        if (!table) return;

        var visibleRows = table.querySelectorAll('tr:not([style*="display: none"])');
        visibleRows.forEach(function(row, index) {
            var numberCell = row.querySelector('.query-number');
            if (numberCell) {
                numberCell.textContent = index + 1;
            }
        });
    }
};

// Hooks Tab Functionality
var MT_Hooks = {
    init: function() {
        this.bindEvents();
    },

    bindEvents: function() {
        var groupFilter = document.getElementById('mt-hooks-group-filter');
        var sortSelect = document.getElementById('mt-hooks-sort');

        if (groupFilter) {
            groupFilter.addEventListener('change', this.filterHooks.bind(this));
        }
        if (sortSelect) {
            sortSelect.addEventListener('change', this.sortHooks.bind(this));
        }
    },

    filterHooks: function() {
        var groupFilter = document.getElementById('mt-hooks-group-filter');
        var table = document.querySelector('.mt-hooks-table tbody');

        if (!table || !groupFilter) return;

        var selectedGroup = groupFilter.value;
        var rows = table.querySelectorAll('tr');
        var visibleCount = 0;

        rows.forEach(function(row) {
            var hookType = row.getAttribute('data-hook-type') || '';
            var showRow = true;

            if (selectedGroup === 'hook' && hookType !== 'action') {
                showRow = false;
            } else if (selectedGroup === 'filter' && hookType !== 'filter') {
                showRow = false;
            }

            if (showRow) {
                row.style.display = '';
                visibleCount++;
                // Update row number
                var numberCell = row.querySelector('.query-number');
                if (numberCell) {
                    numberCell.textContent = visibleCount;
                }
            } else {
                row.style.display = 'none';
            }
        });
    },

    sortHooks: function() {
        var sortSelect = document.getElementById('mt-hooks-sort');
        var table = document.querySelector('.mt-hooks-table tbody');

        if (!table || !sortSelect) return;

        var sortBy = sortSelect.value;
        var rows = Array.from(table.querySelectorAll('tr'));

        rows.sort(function(a, b) {
            var aVal, bVal;

            switch (sortBy) {
                case 'hook':
                    aVal = a.getAttribute('data-hook') || '';
                    bVal = b.getAttribute('data-hook') || '';
                    return aVal.localeCompare(bVal);

                case 'priority':
                    aVal = parseInt(a.getAttribute('data-priority')) || 0;
                    bVal = parseInt(b.getAttribute('data-priority')) || 0;
                    return aVal - bVal; // Ascending

                default:
                    return 0;
            }
        });

        // Reorder rows in table
        rows.forEach(function(row) {
            table.appendChild(row);
        });

        // Update row numbers
        this.updateRowNumbers();
    },

    updateRowNumbers: function() {
        var table = document.querySelector('.mt-hooks-table tbody');
        if (!table) return;

        var visibleRows = table.querySelectorAll('tr:not([style*="display: none"])');
        visibleRows.forEach(function(row, index) {
            var numberCell = row.querySelector('.query-number');
            if (numberCell) {
                numberCell.textContent = index + 1;
            }
        });
    }
};

// Auto-initialize when performance monitor is shown
document.addEventListener('DOMContentLoaded', function() {
    // Initialize universal sortable functionality
    MT_Sortable.init();

    // Wait for performance monitor to be available
    var checkForTabs = setInterval(function() {
        if (document.querySelector('.query-log-table')) {
            // Initialize sortable for all tables
            MT_Sortable.init();

            // Initialize specific tab functionality
            if (document.querySelector('.mt-images-table')) {
                MT_Images.init();
            }
            if (document.querySelector('.mt-hooks-table')) {
                MT_Hooks.init();
            }

            clearInterval(checkForTabs);
        }
    }, 100);
});

// Also initialize when performance bar is opened (for dynamic loading)
if (typeof window.mtPerformanceBar !== 'undefined') {
    var originalToggle = window.mtPerformanceBar.toggle;
    window.mtPerformanceBar.toggle = function() {
        originalToggle.call(this);
        // Delay to ensure content is loaded
        setTimeout(function() {
            MT_Sortable.init();
        }, 200);
    };
}