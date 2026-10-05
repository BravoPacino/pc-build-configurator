(function () {
    'use strict';

    function numberOrNull(input) {
        if (!input || input.value.trim() === '') return null;
        var n = Number(input.value);
        return isFinite(n) ? n : null;
    }

    document.querySelectorAll('form[data-filter-for]').forEach(function (form) {
        var list = document.getElementById(form.getAttribute('data-filter-for'));
        if (!list) return;

        var items  = Array.prototype.slice.call(list.querySelectorAll('[data-filter-item]'));
        var groups = Array.prototype.slice.call(list.querySelectorAll('[data-filter-group]'));
        var count  = list.querySelector('[data-filter-count]');
        var empty  = list.querySelector('[data-filter-empty]');
        var noun   = count && count.getAttribute('data-noun') || 'items';
        var field  = function (name) { return form.querySelector('[data-filter="' + name + '"]'); };

        function currentFilters() {
            var boxes = form.querySelectorAll('input[data-filter="category"]');
            var categories = null;
            if (boxes.length) {
                categories = {};
                boxes.forEach(function (box) { if (box.checked) categories[box.value] = true; });
            } else if (field('category') && field('category').value !== '') {
                categories = {};
                categories[field('category').value] = true;
            }
            var search = field('search');
            return {
                categories: categories,
                brand:  field('brand') ? field('brand').value : '',
                status: field('status') ? field('status').value : '',
                min:    numberOrNull(field('min')),
                max:    numberOrNull(field('max')),
                words:  search ? search.value.toLowerCase().split(/\s+/).filter(Boolean) : []
            };
        }

        function matches(item, f) {
            var d = item.dataset;
            if (f.categories && !f.categories[d.category]) return false;
            if (f.brand && d.brand !== f.brand) return false;
            if (f.status && d.status !== f.status) return false;
            var price = Number(d.price);
            if (f.min !== null && price < f.min) return false;
            if (f.max !== null && price > f.max) return false;
            var text = d.text || '';
            return f.words.every(function (word) { return text.indexOf(word) !== -1; });
        }

        function apply() {
            var f = currentFilters();
            var shown = 0;
            items.forEach(function (item) {
                var on = matches(item, f);
                item.hidden = !on;
                if (on) shown++;
            });
            groups.forEach(function (group) {
                var visible = group.querySelectorAll('[data-filter-item]:not([hidden])').length;
                group.hidden = visible === 0;
                var badge = group.querySelector('[data-group-count]');
                if (badge) badge.textContent = String(visible);
            });
            if (count) count.textContent = 'Showing ' + shown + ' of ' + items.length + ' ' + noun;
            if (empty) empty.hidden = shown !== 0;
        }

        form.addEventListener('input', apply);
        form.addEventListener('change', apply);
        form.addEventListener('reset', function () { setTimeout(apply, 0); });
        form.addEventListener('submit', function (event) { event.preventDefault(); });
        list.addEventListener('click', function (event) {
            if (event.target.closest && event.target.closest('[data-filter-reset]')) form.reset();
        });
        apply();
    });
})();
