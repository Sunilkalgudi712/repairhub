/**
 * RepairHub — Main JavaScript
 * Sidebar toggle, theme switcher, search, AJAX helpers
 */

document.addEventListener('DOMContentLoaded', function () {

    // ═══════════════════════════════════════════════════════
    // Sidebar Toggle (Mobile)
    // ═══════════════════════════════════════════════════════
    const sidebar      = document.getElementById('sidebar');
    const overlay      = document.getElementById('sidebarOverlay');
    const toggleBtn    = document.getElementById('sidebarToggleBtn');

    if (toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        });
    }

    if (overlay) {
        overlay.addEventListener('click', () => {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        });
    }

    // ═══════════════════════════════════════════════════════
    // Theme Toggle (Light / Dark)
    // ═══════════════════════════════════════════════════════
    const themeToggle = document.getElementById('themeToggle');
    const themeIcon   = document.getElementById('themeIcon');

    // Load saved theme
    const savedTheme = localStorage.getItem('rh-theme') || 'light';
    applyTheme(savedTheme);

    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            const current = document.documentElement.getAttribute('data-bs-theme');
            const next = current === 'dark' ? 'light' : 'dark';
            applyTheme(next);
            localStorage.setItem('rh-theme', next);
        });
    }

    function applyTheme(theme) {
        document.documentElement.setAttribute('data-bs-theme', theme);
        if (themeIcon) {
            themeIcon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        }
    }

    // ═══════════════════════════════════════════════════════
    // Global Search (AJAX)
    // ═══════════════════════════════════════════════════════
    const searchInput   = document.getElementById('globalSearch');
    const searchResults = document.getElementById('searchResults');
    let searchTimeout;

    if (searchInput && searchResults) {
        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimeout);
            const query = this.value.trim();

            if (query.length < 2) {
                searchResults.classList.remove('show');
                searchResults.innerHTML = '';
                return;
            }

            searchTimeout = setTimeout(() => {
                const appUrl = (typeof window.APP_URL !== 'undefined') ? window.APP_URL : (document.querySelector('.sidebar-brand a')?.getAttribute('href') || '');
                fetch(appUrl + '/api/search.php?q=' + encodeURIComponent(query))
                    .then(r => r.json())
                    .then(data => {
                        if (data.length === 0) {
                            searchResults.innerHTML = '<div class="p-3 text-muted text-center">No results found</div>';
                        } else {
                            searchResults.innerHTML = data.map(item =>
                                `<a href="${item.url}" class="search-result-item">
                                    <i class="fas ${item.icon} text-primary"></i>
                                    <div>
                                        <div class="fw-semibold">${item.title}</div>
                                        <small class="text-muted">${item.subtitle || ''}</small>
                                    </div>
                                </a>`
                            ).join('');
                        }
                        searchResults.classList.add('show');
                    })
                    .catch(() => {
                        searchResults.classList.remove('show');
                    });
            }, 300);
        });

        // Close search on outside click
        document.addEventListener('click', function (e) {
            if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                searchResults.classList.remove('show');
            }
        });
    }

    // ═══════════════════════════════════════════════════════
    // Auto-dismiss alerts after 5 seconds
    // ═══════════════════════════════════════════════════════
    document.querySelectorAll('.alert-dismissible').forEach(alert => {
        setTimeout(() => {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            if (bsAlert) bsAlert.close();
        }, 5000);
    });

    // ═══════════════════════════════════════════════════════
    // Confirm Delete
    // ═══════════════════════════════════════════════════════
    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', function (e) {
            const msg = this.dataset.confirm || 'Are you sure you want to delete this?';
            if (!confirm(msg)) {
                e.preventDefault();
            }
        });
    });

    // ═══════════════════════════════════════════════════════
    // Tooltips init
    // ═══════════════════════════════════════════════════════
    const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltips.forEach(el => new bootstrap.Tooltip(el));

    // ═══════════════════════════════════════════════════════
    // Dynamic Invoice/Purchase line items
    // ═══════════════════════════════════════════════════════
    window.addLineItem = function (tableBodyId) {
        const tbody = document.getElementById(tableBodyId);
        if (!tbody) return;

        const rowCount = tbody.children.length;
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>
                <input type="text" name="items[${rowCount}][description]" class="form-control form-control-sm" placeholder="Description" required>
            </td>
            <td style="width:100px">
                <input type="number" name="items[${rowCount}][quantity]" class="form-control form-control-sm item-qty" value="1" min="1" required>
            </td>
            <td style="width:130px">
                <input type="number" name="items[${rowCount}][unit_price]" class="form-control form-control-sm item-price" step="0.01" value="0" required>
            </td>
            <td style="width:130px">
                <input type="text" class="form-control form-control-sm item-total" value="0.00" readonly>
            </td>
            <td style="width:50px">
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove(); calculateTotals();">
                    <i class="fas fa-times"></i>
                </button>
            </td>
        `;
        tbody.appendChild(row);
        bindLineItemEvents(row);
    };

    // Bind line item calculation events
    function bindLineItemEvents(row) {
        const qty = row.querySelector('.item-qty');
        const price = row.querySelector('.item-price');
        if (qty && price) {
            qty.addEventListener('input', calculateTotals);
            price.addEventListener('input', calculateTotals);
        }
    }

    // Bind existing rows
    document.querySelectorAll('#invoiceItems tr, #purchaseItems tr').forEach(bindLineItemEvents);

    // Calculate totals
    window.calculateTotals = function () {
        let subtotal = 0;
        document.querySelectorAll('#invoiceItems tr, #purchaseItems tr').forEach(row => {
            const qty = parseFloat(row.querySelector('.item-qty')?.value) || 0;
            const price = parseFloat(row.querySelector('.item-price')?.value) || 0;
            const total = qty * price;
            const totalField = row.querySelector('.item-total');
            if (totalField) totalField.value = total.toFixed(2);
            subtotal += total;
        });

        const subtotalEl = document.getElementById('subtotal');
        const taxRateEl  = document.getElementById('taxRate');
        const taxAmtEl   = document.getElementById('taxAmount');
        const discountEl = document.getElementById('discountAmount');
        const grandTotal = document.getElementById('grandTotal');

        if (subtotalEl) subtotalEl.textContent = subtotal.toFixed(2);

        const taxRate  = parseFloat(taxRateEl?.value) || 0;
        const taxAmt   = subtotal * (taxRate / 100);
        if (taxAmtEl) taxAmtEl.textContent = taxAmt.toFixed(2);

        const discount = parseFloat(discountEl?.value) || 0;
        const total    = subtotal + taxAmt - discount;
        if (grandTotal) grandTotal.textContent = total.toFixed(2);
    };

    // ═══════════════════════════════════════════════════════
    // Customer autocomplete for ticket/invoice forms
    // ═══════════════════════════════════════════════════════
    const customerSearch = document.getElementById('customerSearch');
    const customerResults = document.getElementById('customerResults');
    const customerIdField = document.getElementById('customerId');

    if (customerSearch && customerResults) {
        let csTimeout;
        customerSearch.addEventListener('input', function () {
            clearTimeout(csTimeout);
            const q = this.value.trim();
            if (q.length < 2) { customerResults.innerHTML = ''; return; }

            csTimeout = setTimeout(() => {
                const appUrl = (typeof window.APP_URL !== 'undefined') ? window.APP_URL : '';
                fetch(appUrl + '/api/customers.php?search=' + encodeURIComponent(q))
                    .then(r => r.json())
                    .then(data => {
                        customerResults.innerHTML = data.map(c =>
                            `<a href="#" class="list-group-item list-group-item-action customer-option" 
                                data-id="${c.id}" data-name="${c.name}" data-phone="${c.phone}">
                                <strong>${c.name}</strong> <small class="text-muted">— ${c.phone}</small>
                            </a>`
                        ).join('') || '<div class="list-group-item text-muted">No customers found</div>';
                    });
            }, 300);
        });

        customerResults.addEventListener('click', function (e) {
            const opt = e.target.closest('.customer-option');
            if (opt) {
                e.preventDefault();
                customerSearch.value = opt.dataset.name + ' — ' + opt.dataset.phone;
                if (customerIdField) customerIdField.value = opt.dataset.id;
                customerResults.innerHTML = '';
            }
        });
    }

});
