(function () {
    'use strict';

    var select = document.getElementById('f-category_id');
    if (!select) return;

    var wattage = document.querySelector('label[for="f-wattage"]');

    function apply() {
        var option = select.options[select.selectedIndex];
        var fields = option ? (option.getAttribute('data-fields') || '').split(' ') : [];
        if (wattage) wattage.textContent = option && option.getAttribute('data-label-wattage') || 'Wattage (W)';
        document.querySelectorAll('[data-field]').forEach(function (wrapper) {
            var name = wrapper.getAttribute('data-field');
            var used = fields.indexOf(name) !== -1;
            wrapper.hidden = !used;
            wrapper.querySelectorAll('input, select').forEach(function (control) { control.disabled = !used; });
            var label = option && option.getAttribute('data-label-' + name.replace(/_/g, '-'));
            if (label) wrapper.querySelector('label').textContent = label;
        });
    }

    select.addEventListener('change', apply);
    apply();
})();
