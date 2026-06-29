document.addEventListener('DOMContentLoaded', function() {
    // ===== Modal Functions =====
    window.openModal = function(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
    };

    window.closeModal = function(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    };

    // Close modal on outside click
    window.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    });

    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal').forEach(modal => {
                modal.style.display = 'none';
            });
            document.body.style.overflow = 'auto';
        }
    });

    // ===== Delete Confirmation =====
    window.confirmDelete = function(message) {
        return confirm(message || 'Are you sure you want to delete this item? This action cannot be undone.');
    };

    // ===== Form Validation =====
    window.validateForm = function(formId) {
        const form = document.getElementById(formId);
        if (!form) return true;

        const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
        let isValid = true;

        inputs.forEach(input => {
            if (!input.value.trim()) {
                input.style.borderColor = '#dc3545';
                isValid = false;
            } else {
                input.style.borderColor = '#dee2e6';
            }
        });

        if (!isValid) {
            alert('Please fill in all required fields.');
        }

        return isValid;
    };

    // ===== Image Preview =====
    window.previewImage = function(input, previewId) {
        const preview = document.getElementById(previewId);
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            };
            reader.readAsDataURL(input.files[0]);
        }
    };

    // ===== Toggle Password Visibility =====
    document.querySelectorAll('.toggle-password').forEach(button => {
        button.addEventListener('click', function() {
            const input = this.parentElement.querySelector('input');
            if (input) {
                const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                input.setAttribute('type', type);
                this.textContent = type === 'password' ? 'Show' : 'Hide';
            }
        });
    });

    // ===== Search Filter =====
    window.filterTable = function(inputId, tableId) {
        const input = document.getElementById(inputId);
        const table = document.getElementById(tableId);
        if (!input || !table) return;

        const filter = input.value.toLowerCase();
        const rows = table.querySelectorAll('tbody tr');

        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(filter) ? '' : 'none';
        });
    };

    // ===== Select All Checkbox =====
    window.toggleSelectAll = function(selectAllId, checkboxClass) {
        const selectAll = document.getElementById(selectAllId);
        if (!selectAll) return;

        const checkboxes = document.querySelectorAll('.' + checkboxClass);
        checkboxes.forEach(cb => {
            cb.checked = selectAll.checked;
        });
    };

    // ===== Bulk Actions =====
    window.bulkAction = function(action, checkboxClass) {
        const checkboxes = document.querySelectorAll('.' + checkboxClass + ':checked');
        if (checkboxes.length === 0) {
            alert('Please select at least one item.');
            return;
        }

        const ids = Array.from(checkboxes).map(cb => cb.value);
        if (confirm('Are you sure you want to ' + action + ' selected items?')) {
            // Submit form with bulk action
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = window.location.href;

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'bulk_action';
            actionInput.value = action;
            form.appendChild(actionInput);

            const idsInput = document.createElement('input');
            idsInput.type = 'hidden';
            idsInput.name = 'ids';
            idsInput.value = ids.join(',');
            form.appendChild(idsInput);

            document.body.appendChild(form);
            form.submit();
        }
    };

    // ===== Status Update =====
    window.updateStatus = function(url, id, status) {
        if (!confirm('Update status to ' + status + '?')) return;

        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: id, status: status })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Failed to update status.');
            }
        })
        .catch(error => {
            alert('Error: ' + error);
        });
    };

    // ===== Chart Initialization (if Chart.js is loaded) =====
    if (typeof Chart !== 'undefined') {
        // Revenue Chart
        const revenueCtx = document.getElementById('revenueChart');
        if (revenueCtx) {
            new Chart(revenueCtx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                    datasets: [{
                        label: 'Revenue',
                        data: [12000, 19000, 15000, 25000, 22000, 30000],
                        borderColor: '#05573c',
                        backgroundColor: 'rgba(5, 87, 60, 0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        }

        // Orders Chart
        const ordersCtx = document.getElementById('ordersChart');
        if (ordersCtx) {
            new Chart(ordersCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'],
                    datasets: [{
                        data: [12, 19, 8, 25, 5],
                        backgroundColor: ['#ffc107', '#17a2b8', '#007bff', '#28a745', '#dc3545']
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
    }

    // ===== Toast Notification =====
    window.showToast = function(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = 'toast toast-' + type;
        toast.innerHTML = `
            <div class="toast-content">
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                <span>${message}</span>
            </div>
            <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>
        `;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.classList.add('show');
        }, 100);
        
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    };

    // ===== Initialize DataTables =====
    if (typeof jQuery !== 'undefined' && jQuery.fn.DataTable) {
        $('.datatable').DataTable({
            responsive: true,
            pageLength: 25,
            language: {
                search: 'Search:',
                lengthMenu: 'Show _MENU_ entries',
                info: 'Showing _START_ to _END_ of _TOTAL_ entries',
                infoEmpty: 'No entries found',
                infoFiltered: '(filtered from _MAX_ total entries)'
            }
        });
    }

    // ===== Auto-hide alerts =====
    setTimeout(() => {
        document.querySelectorAll('.alert:not(.alert-persistent)').forEach(alert => {
            alert.style.transition = 'opacity 0.5s ease';
            setTimeout(() => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            }, 5000);
        });
    }, 1000);
});
