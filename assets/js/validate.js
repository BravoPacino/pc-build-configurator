(function () {
    'use strict';

    function problemWith(field) {
        var v = field.validity;
        var d = field.dataset;

        if (v.valueMissing)    return d.errorRequired  || 'This field is required.';
        if (v.typeMismatch)    return d.errorType      || (field.type === 'email' ? 'Enter a valid email address.' : 'Enter a valid value.');
        if (v.badInput)        return d.errorType      || 'Enter a number.';
        if (v.tooShort)        return d.errorMinlength || 'Use at least ' + field.minLength + ' characters.';
        if (v.tooLong)         return d.errorMaxlength || 'Use no more than ' + field.maxLength + ' characters.';
        if (v.patternMismatch) return d.errorPattern   || 'This is not in the expected format.';
        if (v.rangeUnderflow)  return d.errorMin       || 'The lowest value allowed is ' + field.min + '.';
        if (v.rangeOverflow)   return d.errorMax       || 'The highest value allowed is ' + field.max + '.';
        if (v.stepMismatch)    return d.errorStep      || 'Enter a valid number.';

        if (field.minLength > 0 && field.value.length > 0 && field.value.length < field.minLength) {
            return d.errorMinlength || 'Use at least ' + field.minLength + ' characters.';
        }

        if (d.match) {
            var other = field.form.elements.namedItem(d.match);
            if (other && other.value !== field.value) {
                return d.errorMatch || 'The two values do not match.';
            }
        }
        return '';
    }

    function show(field, message) {
        var wrapper = field.closest('.field');
        if (!wrapper) return;

        var box = wrapper.querySelector('.field-error');
        if (!box) {
            box = document.createElement('div');
            box.className = 'field-error';
            wrapper.appendChild(box);
        }
        box.textContent = message;
        wrapper.classList.toggle('has-error', message !== '');
        if (message) {
            field.setAttribute('aria-invalid', 'true');
        } else {
            field.removeAttribute('aria-invalid');
        }
    }

    function check(field) {
        var message = problemWith(field);
        show(field, message);
        return message === '';
    }

    function isShowingProblem(field) {
        var wrapper = field.closest('.field');
        return wrapper !== null && wrapper.classList.contains('has-error');
    }

    function checkableFields(form) {
        return Array.prototype.filter.call(form.elements, function (el) {
            return /^(INPUT|SELECT|TEXTAREA)$/.test(el.tagName) && el.willValidate;
        });
    }

    function inValidatedForm(el) {
        return el.form instanceof HTMLFormElement && el.form.hasAttribute('data-validate');
    }

    document.querySelectorAll('form[data-validate]').forEach(function (form) {
        form.noValidate = true;
    });

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-validate')) return;
        if (event.submitter && event.submitter.formNoValidate) return;

        var firstProblem = null;
        checkableFields(form).forEach(function (field) {
            if (!check(field) && firstProblem === null) firstProblem = field;
        });
        if (firstProblem !== null) {
            event.preventDefault();
            firstProblem.focus();
        }
    });

    document.addEventListener('focusout', function (event) {
        var field = event.target;
        if (!inValidatedForm(field) || field.value === '') return;
        check(field);
    });

    document.addEventListener('input', function (event) {
        var field = event.target;
        if (!inValidatedForm(field)) return;

        if (isShowingProblem(field)) check(field);

        var matching = field.form.querySelectorAll('[data-match="' + CSS.escape(field.name) + '"]');
        matching.forEach(function (other) {
            if (isShowingProblem(other)) check(other);
        });
    });
})();
