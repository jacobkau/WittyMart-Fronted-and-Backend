// ===== SIDEBAR TOGGLE (Mobile) =====
function toggleSidebar() {
    const sidebar = document.getElementById('adminSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    sidebar.classList.toggle('open');
    overlay.classList.toggle('active');
}

function closeSidebar() {
    const sidebar = document.getElementById('adminSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    sidebar.classList.remove('open');
    overlay.classList.remove('active');
}

// Close sidebar when clicking outside
document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('adminSidebar');
    const toggle = document.querySelector('.sidebar-toggle');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (sidebar && toggle && overlay) {
        if (!sidebar.contains(event.target) && !toggle.contains(event.target)) {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
        }
    }
});

// Close sidebar on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeSidebar();
    }
});

// ===== MODAL FUNCTIONS =====
function openModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

// Close modal on outside click
document.addEventListener('click', function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
});

// ===== CONFIRM DELETE =====
function confirmDelete(message) {
    return confirm(message || 'Are you sure you want to delete this item?');
}
       
        // Auto-hide errors after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alert = document.querySelector('.alert');
            if (alert) {
                setTimeout(() => {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        alert.style.display = 'none';
                    }, 500);
                }, 5000);
            }
        });
        
       
        
        // ===== KEYBOARD SHORTCUT: Enter to submit =====
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                const form = document.getElementById('loginForm');
                if (form) {
                    form.dispatchEvent(new Event('submit'));
                }
            }
        });

/**
 * Filter table by search input
 */
function filterTable(inputId, tableId, delay = 300) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    
    if (!input || !table) return;

    if (input._timeout) clearTimeout(input._timeout);

    input._timeout = setTimeout(function() {
        const filter = input.value.toLowerCase().trim();
        const rows = table.querySelectorAll('tbody tr');
        let visibleCount = 0;

        rows.forEach(row => {
            const match = row.textContent.toLowerCase().includes(filter);
            row.style.display = match ? '' : 'none';
            if (match) visibleCount++;
        });

        updateResults(table, visibleCount);
    }, delay);
}

/**
 * Filter table by specific columns
 */
function filterTableByColumns(inputId, tableId, columnIndexes = null) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    
    if (!input || !table) return;

    const filter = input.value.toLowerCase().trim();
    const rows = table.querySelectorAll('tbody tr');
    let visibleCount = 0;

    rows.forEach(row => {
        let match = false;
        const cells = row.querySelectorAll('td');
        
        if (columnIndexes) {
            columnIndexes.forEach(index => {
                if (cells[index] && cells[index].textContent.toLowerCase().includes(filter)) {
                    match = true;
                }
            });
        } else {
            match = row.textContent.toLowerCase().includes(filter);
        }
        
        row.style.display = match ? '' : 'none';
        if (match) visibleCount++;
    });

    updateResults(table, visibleCount);
}

/**
 * Update results count
 */
function updateResults(table, visibleCount) {
    const totalRows = table.querySelectorAll('tbody tr').length;
    const counter = table.parentElement.querySelector('.result-count');
    if (counter) {
        counter.textContent = `Showing ${visibleCount} of ${totalRows} results`;
    }
    
    const noResultMsg = table.parentElement.querySelector('.no-results-message');
    if (noResultMsg) {
        noResultMsg.style.display = visibleCount === 0 ? 'block' : 'none';
    }
}

/**
 * Clear search
 */
function clearSearch(inputId, tableId) {
    const input = document.getElementById(inputId);
    if (input) {
        input.value = '';
        filterTable(inputId, tableId);
        input.focus();
    }
}

/**
 * Export table to CSV
 */
function exportTableToCSV(tableId, filename = 'export.csv') {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    rows.forEach(row => {
        const rowData = [];
        const cells = row.querySelectorAll('th, td');
        cells.forEach(cell => {
            let text = cell.textContent.trim();
            // Remove extra spaces
            text = text.replace(/\s+/g, ' ');
            // Escape quotes
            text = text.replace(/"/g, '""');
            rowData.push(`"${text}"`);
        });
        csv.push(rowData.join(','));
    });
    
    const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    window.URL.revokeObjectURL(url);
}

/**
 * Print table
 */
function printTable(tableId) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
            <head>
                <title>Print</title>
                <style>
                    body { font-family: Arial, sans-serif; padding: 20px; }
                    table { width: 100%; border-collapse: collapse; }
                    th, td { padding: 8px 12px; border: 1px solid #ddd; text-align: left; }
                    th { background: #f5f5f5; }
                </style>
            </head>
            <body>
                ${table.outerHTML}
            </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}
