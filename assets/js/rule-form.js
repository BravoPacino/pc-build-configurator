(function () {
    'use strict';

    var kind = document.getElementById('f-rule_type');
    var attribute = document.getElementById('f-attribute_key');
    if (!kind || !attribute) return;

    function show(name, on) {
        var wrapper = document.querySelector('[data-rule-field="' + name + '"]');
        if (!wrapper) return;
        wrapper.hidden = !on;
        wrapper.querySelectorAll('input, select').forEach(function (control) { control.disabled = !on; });
    }

    function relabel(id, text) {
        var label = document.querySelector('label[for="' + id + '"]');
        if (label && text) label.textContent = text;
    }

    function apply() {
        var option = kind.options[kind.selectedIndex];
        var chosen = kind.value;

        show('second', chosen !== '' && option.getAttribute('data-second') === 'yes');
        show('headroom', chosen !== '' && option.getAttribute('data-headroom') === 'yes');
        if (chosen !== '') {
            relabel('f-category_a', option.getAttribute('data-label-a'));
            relabel('f-category_b', option.getAttribute('data-label-b'));
            relabel('f-attribute_key', option.getAttribute('data-label-attribute'));
        }

        var allowed = [];
        Array.prototype.forEach.call(attribute.options, function (item) {
            if (item.value === '') return;
            var fits = chosen === '' || item.getAttribute('data-type') === chosen;
            item.hidden = !fits;
            item.disabled = !fits;
            if (fits) allowed.push(item.value);
        });
        if (allowed.indexOf(attribute.value) === -1) attribute.value = '';
        if (allowed.length === 1 && chosen !== '') attribute.value = allowed[0];
    }

    kind.addEventListener('change', apply);
    apply();
})();
