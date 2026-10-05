(function () {
    'use strict';

    var dialog = null;
    var pending = null;

    function build() {
        dialog = document.createElement('dialog');
        dialog.className = 'confirm-dialog';
        dialog.setAttribute('aria-labelledby', 'confirm-title');
        dialog.innerHTML =
            '<h2 class="confirm-title" id="confirm-title"></h2>' +
            '<p class="confirm-text"></p>' +
            '<div class="confirm-actions">' +
            '<button type="button" class="btn btn-ghost" data-answer="cancel">Cancel</button>' +
            '<button type="button" class="btn btn-primary" data-answer="confirm"></button>' +
            '</div>';
        document.body.appendChild(dialog);

        dialog.addEventListener('click', function (event) {
            var button = event.target.closest && event.target.closest('[data-answer]');
            if (button) dialog.close(button.getAttribute('data-answer'));
        });
        dialog.addEventListener('close', function () {
            var form = pending;
            pending = null;
            if (form && dialog.returnValue === 'confirm') {
                form.dataset.confirmed = 'yes';
                form.requestSubmit();
            }
        });
    }

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirm')) return;
        if (form.dataset.confirmed === 'yes') {
            delete form.dataset.confirmed;
            return;
        }
        event.preventDefault();
        if (!dialog) build();
        pending = form;
        dialog.returnValue = '';
        dialog.querySelector('.confirm-title').textContent = form.getAttribute('data-confirm-title') || 'Are you sure?';
        dialog.querySelector('.confirm-text').textContent = form.getAttribute('data-confirm');
        dialog.querySelector('[data-answer="confirm"]').textContent = form.getAttribute('data-confirm-button') || 'Confirm';
        dialog.querySelector('[data-answer="cancel"]').textContent = form.getAttribute('data-confirm-dismiss') || 'Cancel';
        dialog.showModal();
        dialog.querySelector('[data-answer="cancel"]').focus();
    });
})();
