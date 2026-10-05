(function () {
    'use strict';

    var realFetch = window.fetch.bind(window);
    var data = realFetch('data.json').then(function (response) { return response.json(); });

    window.fetch = function (input, init) {
        var url = new URL(typeof input === 'string' ? input : input.url, window.location.href);
        if (!/\/api\/check\.php$/.test(url.pathname)) return realFetch(input, init);
        return data.then(function (catalogue) {
            var componentIds = [];
            var quantities = {};
            url.searchParams.forEach(function (value, key) {
                var match = /^([cq])\[(\d+)\]$/.exec(key);
                if (!match) return;
                if (match[1] === 'c') componentIds.push([match[2], value]);
                else quantities[match[2]] = value;
            });
            var answer = window.PCDemoEngine.check(catalogue, componentIds, quantities, url.searchParams.get('view'));
            return new Response(JSON.stringify(answer.body), {
                status: answer.status,
                headers: { 'Content-Type': 'application/json' }
            });
        });
    };

    var form = document.querySelector('form[data-configurator]');
    if (!form) return;
    form.addEventListener('submit', function (event) {
        event.preventDefault();
        event.stopImmediatePropagation();
        var box = form.querySelector('.summary-save');
        var note = box.querySelector('[data-demo-note]');
        if (!note) {
            note = document.createElement('p');
            note.className = 'flash flash-info';
            note.setAttribute('role', 'status');
            note.setAttribute('data-demo-note', '');
            box.appendChild(note);
        }
        note.textContent = 'Saving and ordering need the full system: an account, and the shop’s database to reserve the parts. The demo stops here.';
    }, true);
})();
