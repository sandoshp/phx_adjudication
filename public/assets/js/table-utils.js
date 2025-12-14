/**
 * PHOENIX Adjudication - Table Utilities
 * Provides sortable and searchable functionality for tables
 */

/**
 * Make a table sortable by clicking on headers
 * @param {string} tableSelector - CSS selector for the table
 */
function makeTableSortable(tableSelector) {
    const table = document.querySelector(tableSelector);
    if (!table) return;

    const headers = table.querySelectorAll('thead th');
    const tbody = table.querySelector('tbody');

    headers.forEach((header, index) => {
        // Skip if header has no-sort class
        if (header.classList.contains('no-sort')) return;

        // Add sort indicator
        header.style.cursor = 'pointer';
        header.style.userSelect = 'none';
        header.innerHTML += ' <i class="material-icons tiny sort-icon" style="vertical-align: middle; opacity: 0.3;">unfold_more</i>';

        let ascending = true;

        header.addEventListener('click', () => {
            // Remove sort indicators from other headers
            headers.forEach(h => {
                const icon = h.querySelector('.sort-icon');
                if (icon && h !== header) {
                    icon.textContent = 'unfold_more';
                    icon.style.opacity = '0.3';
                }
            });

            // Update this header's icon
            const icon = header.querySelector('.sort-icon');
            icon.textContent = ascending ? 'arrow_upward' : 'arrow_downward';
            icon.style.opacity = '1';

            // Get all rows
            const rows = Array.from(tbody.querySelectorAll('tr'));

            // Sort rows
            rows.sort((a, b) => {
                const aCell = a.querySelectorAll('td')[index];
                const bCell = b.querySelectorAll('td')[index];

                if (!aCell || !bCell) return 0;

                let aValue = aCell.textContent.trim();
                let bValue = bCell.textContent.trim();

                // Try to parse as number
                const aNum = parseFloat(aValue.replace(/[^0-9.-]/g, ''));
                const bNum = parseFloat(bValue.replace(/[^0-9.-]/g, ''));

                if (!isNaN(aNum) && !isNaN(bNum)) {
                    return ascending ? aNum - bNum : bNum - aNum;
                }

                // Try to parse as date
                const aDate = new Date(aValue);
                const bDate = new Date(bValue);

                if (!isNaN(aDate.getTime()) && !isNaN(bDate.getTime())) {
                    return ascending ? aDate - bDate : bDate - aDate;
                }

                // String comparison
                return ascending
                    ? aValue.localeCompare(bValue)
                    : bValue.localeCompare(aValue);
            });

            // Re-append rows in new order
            rows.forEach(row => tbody.appendChild(row));

            ascending = !ascending;
        });
    });
}

/**
 * Add search functionality to a table
 * @param {string} inputSelector - CSS selector for the search input
 * @param {string} tableSelector - CSS selector for the table
 * @param {number[]} searchColumns - Array of column indices to search (null = search all)
 */
function makeTableSearchable(inputSelector, tableSelector, searchColumns = null) {
    const input = document.querySelector(inputSelector);
    const table = document.querySelector(tableSelector);

    if (!input || !table) return;

    const tbody = table.querySelector('tbody');

    input.addEventListener('input', (e) => {
        const searchTerm = e.target.value.toLowerCase();
        const rows = tbody.querySelectorAll('tr');

        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            let found = false;

            // Determine which columns to search
            const columnsToSearch = searchColumns || Array.from(cells.keys());

            for (const index of columnsToSearch) {
                const cell = cells[index];
                if (cell && cell.textContent.toLowerCase().includes(searchTerm)) {
                    found = true;
                    break;
                }
            }

            row.style.display = found ? '' : 'none';
        });

        // Show/hide "no results" message
        const visibleRows = Array.from(rows).filter(r => r.style.display !== 'none');
        let noResultsRow = tbody.querySelector('.no-results-row');

        if (visibleRows.length === 0) {
            if (!noResultsRow) {
                noResultsRow = document.createElement('tr');
                noResultsRow.className = 'no-results-row';
                noResultsRow.innerHTML = `
                    <td colspan="100" class="center-align grey-text" style="padding: 40px;">
                        <i class="material-icons" style="font-size: 48px;">search_off</i>
                        <p>No results found for "${e.target.value}"</p>
                    </td>
                `;
                tbody.appendChild(noResultsRow);
            }
        } else {
            if (noResultsRow) {
                noResultsRow.remove();
            }
        }
    });
}

/**
 * Initialize both sorting and searching for a table
 * @param {string} tableSelector - CSS selector for the table
 * @param {string} searchInputSelector - CSS selector for search input (optional)
 * @param {number[]} searchColumns - Columns to search (optional, null = all)
 */
function initializeTable(tableSelector, searchInputSelector = null, searchColumns = null) {
    makeTableSortable(tableSelector);

    if (searchInputSelector) {
        makeTableSearchable(searchInputSelector, tableSelector, searchColumns);
    }
}

// Export functions for use in modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        makeTableSortable,
        makeTableSearchable,
        initializeTable
    };
}
