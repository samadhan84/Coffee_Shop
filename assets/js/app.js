// Coffee Shop Management System — client-side helpers.
(function () {
    'use strict';

    // Mobile menu / sidebar toggles.
    document.querySelectorAll('[data-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var target = document.getElementById(btn.dataset.toggle);
            if (target) target.classList.toggle('open');
        });
    });

    // Transparent home navbar turns solid on scroll.
    var navbar = document.querySelector('.navbar-transparent');
    if (navbar) {
        var onScroll = function () { navbar.classList.toggle('scrolled', window.scrollY > 40); };
        window.addEventListener('scroll', onScroll);
        onScroll();
    }

    // Confirmation before destructive actions.
    document.querySelectorAll('button[data-confirm]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            var select = btn.dataset.confirmSelect && btn.form.querySelector('select');
            if (select && select.value !== btn.dataset.confirmSelect) return;
            if (!confirm(btn.dataset.confirm)) e.preventDefault();
        });
    });

    // Selects/radios that submit their form on change.
    document.querySelectorAll('[data-autosubmit]').forEach(function (el) {
        var initial = el.value;
        el.addEventListener('change', function () {
            if (el.dataset.confirmValue && el.value === el.dataset.confirmValue && !confirm(el.dataset.confirm)) {
                el.value = initial;
                return;
            }
            el.form.submit();
        });
    });

    // ----- POS screen -----
    var form = document.getElementById('pos-form');
    if (!form) return;

    var cart = {};          // id -> {name, price, qty}
    var taxRate = parseFloat(form.dataset.tax) || 0;
    var currency = form.dataset.currency || '₹';
    var lines = document.getElementById('pos-lines');
    var billBox = document.getElementById('bill-box');
    var itemsInput = document.getElementById('pos-items');
    var submit = document.getElementById('pos-submit');
    var discount = document.getElementById('discount');
    var extra = document.getElementById('extra');

    function money(n) { return currency + n.toFixed(2); }

    function escapeHtml(s) {
        return s.replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    // Same formula as calculate_bill() in includes/orders.php (the server recalculates anyway).
    function render() {
        var ids = Object.keys(cart);
        var subtotal = 0;
        lines.innerHTML = '';
        if (!ids.length) {
            lines.innerHTML = '<li class="empty">Tap menu items to add them.</li>';
        }
        ids.forEach(function (id) {
            var it = cart[id];
            subtotal += it.price * it.qty;
            var li = document.createElement('li');
            li.innerHTML = '<span class="name">' + escapeHtml(it.name) + '</span>' +
                '<button type="button" data-dec="' + id + '">−</button><strong>' + it.qty + '</strong>' +
                '<button type="button" data-inc="' + id + '">+</button>' +
                '<span>' + money(it.price * it.qty) + '</span>';
            lines.appendChild(li);
        });
        var pct = Math.min(100, Math.max(0, parseFloat(discount.value) || 0));
        var disc = Math.round(subtotal * pct) / 100;
        var tax = Math.round((subtotal - disc) * taxRate) / 100;
        var ext = Math.max(0, parseFloat(extra.value) || 0);
        billBox.innerHTML =
            '<div><span>Subtotal</span><span>' + money(subtotal) + '</span></div>' +
            '<div><span>Discount (' + pct + '%)</span><span>-' + money(disc) + '</span></div>' +
            '<div><span>Tax (' + taxRate + '%)</span><span>' + money(tax) + '</span></div>' +
            '<div><span>Extra charges</span><span>' + money(ext) + '</span></div>' +
            '<div class="bill-total"><span>Total</span><span>' + money(subtotal - disc + tax + ext) + '</span></div>';
        var payload = {};
        ids.forEach(function (id) { payload[id] = cart[id].qty; });
        itemsInput.value = JSON.stringify(payload);
        submit.disabled = !ids.length;
    }

    document.querySelectorAll('.pos-item').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.dataset.id;
            cart[id] = cart[id] || { name: btn.dataset.name, price: parseFloat(btn.dataset.price), qty: 0 };
            cart[id].qty++;
            render();
        });
    });

    lines.addEventListener('click', function (e) {
        var inc = e.target.dataset.inc, dec = e.target.dataset.dec;
        if (inc) cart[inc].qty++;
        if (dec && --cart[dec].qty <= 0) delete cart[dec];
        render();
    });

    [discount, extra].forEach(function (el) { el.addEventListener('input', render); });

    // Table number only matters for dine-in.
    var tableField = document.getElementById('table-field');
    form.querySelectorAll('input[name=order_type]').forEach(function (r) {
        r.addEventListener('change', function () {
            tableField.style.display = form.order_type.value === 'dine_in' ? '' : 'none';
        });
    });

    // Hide quick-add customer fields when an existing customer is picked.
    var customerSelect = document.getElementById('customer-select');
    var newCustomer = document.getElementById('new-customer');
    customerSelect.addEventListener('change', function () {
        newCustomer.style.display = customerSelect.value ? 'none' : '';
    });

    // Menu search and category filter.
    var search = document.getElementById('pos-search');
    var activeCat = '';
    function filter() {
        var q = search.value.toLowerCase();
        document.querySelectorAll('.pos-item').forEach(function (btn) {
            var ok = (!activeCat || btn.dataset.cat === activeCat) && btn.dataset.name.toLowerCase().indexOf(q) !== -1;
            btn.style.display = ok ? '' : 'none';
        });
    }
    search.addEventListener('input', filter);
    document.querySelectorAll('.chip[data-cat]').forEach(function (chip) {
        chip.addEventListener('click', function () {
            document.querySelectorAll('.chip[data-cat]').forEach(function (c) { c.classList.remove('active'); });
            chip.classList.add('active');
            activeCat = chip.dataset.cat;
            filter();
        });
    });

    render();
})();
