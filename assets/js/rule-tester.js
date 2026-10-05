(function () {
    'use strict';

    var form = document.querySelector('form[data-rule-tester]');
    if (!form) return;
    var results = document.querySelector('[data-tester-results]');
    var address = form.getAttribute('data-check-url');
    var latest = 0;

    function note(text, className) {
        var p = document.createElement('p');
        p.className = className;
        p.textContent = text;
        results.replaceChildren(p);
    }

    function problems(list) {
        var ul = document.createElement('ul');
        ul.className = 'form-errors';
        list.forEach(function (text) {
            var li = document.createElement('li');
            li.textContent = text;
            ul.appendChild(li);
        });
        results.replaceChildren(ul);
    }

    function anythingChosen() {
        return Array.prototype.some.call(form.querySelectorAll('select[name^="c["]'), function (select) {
            return select.value !== '';
        });
    }

    function check() {
        var ticket = ++latest;
        if (!anythingChosen()) {
            note('Choose a part to see how each rule judges it.', 'tester-empty');
            return;
        }
        var query = new URLSearchParams(new FormData(form)).toString();
        results.setAttribute('aria-busy', 'true');
        fetch(address + '?' + query, { headers: { Accept: 'application/json' } })
            .then(function (response) {
                return response.json().then(function (data) { return { ok: response.ok, data: data }; });
            })
            .then(function (answer) {
                if (ticket !== latest) return;
                if (answer.ok) results.innerHTML = answer.data.html;
                else problems(answer.data.errors || [answer.data.error || 'The check was refused.']);
            })
            .catch(function () {
                if (ticket === latest) note('The check could not be completed. Reload the page and try again.', 'field-error');
            })
            .then(function () {
                if (ticket === latest) results.removeAttribute('aria-busy');
            });
    }

    form.addEventListener('change', check);
    form.addEventListener('submit', function (event) {
        event.preventDefault();
        check();
    });
    form.addEventListener('click', function (event) {
        var clear = event.target.closest && event.target.closest('[data-tester-clear]');
        if (!clear) return;
        event.preventDefault();
        form.querySelectorAll('select').forEach(function (select) { select.selectedIndex = 0; });
        check();
    });
})();
