(function (root) {
    'use strict';

    var RULE_ATTRIBUTES = {
        ATTRIBUTE_MATCH: ['socket', 'memory_type'],
        CAPACITY_CHECK: ['wattage', 'memory_slots', 'm2_slots', 'sata_ports', 'drive_bays'],
        REQUIRES_CATEGORY: ['has_integrated_graphics', 'includes_cooler']
    };

    var ATTRIBUTE_LABELS = {
        socket: 'socket',
        memory_type: 'memory type',
        wattage: 'wattage',
        memory_slots: 'memory slots',
        m2_slots: 'M.2 slots',
        sata_ports: 'SATA ports',
        drive_bays: 'drive bays',
        has_integrated_graphics: 'integrated graphics',
        includes_cooler: 'cooler in the box'
    };

    var COUNT_UNITS = {
        memory_slots: ['memory slot', 'memory slots'],
        m2_slots: ['M.2 slot', 'M.2 slots'],
        sata_ports: ['SATA port', 'SATA ports'],
        drive_bays: ['drive bay', 'drive bays']
    };

    var FLAG_PHRASES = {
        has_integrated_graphics: ['has integrated graphics', 'has no integrated graphics'],
        includes_cooler: ['comes with a cooler', 'comes without a cooler']
    };

    function toInt(value) {
        if (value === null || value === undefined || value === false) return 0;
        if (value === true) return 1;
        if (typeof value === 'number') return Math.trunc(value);
        var n = parseInt(String(value).trim(), 10);
        return isNaN(n) ? 0 : n;
    }

    function toFloat(value) {
        if (value === null || value === undefined) return 0;
        var n = parseFloat(value);
        return isNaN(n) ? 0 : n;
    }

    function toStr(value) {
        return value === null || value === undefined ? '' : String(value);
    }

    function upperTrim(value) {
        return toStr(value).replace(/^[ \t\n\r\0\x0B]+|[ \t\n\r\0\x0B]+$/g, '').toUpperCase();
    }

    function phpRound(value, places) {
        var factor = Math.pow(10, places);
        var scaled = parseFloat((Math.abs(value) * factor).toPrecision(15));
        var rounded = Math.floor(scaled + 0.5) / factor;
        return value < 0 ? -rounded : rounded;
    }

    function numberFormat(value, decimals, thousands) {
        var fixed = phpRound(value, decimals).toFixed(decimals);
        var negative = fixed.charAt(0) === '-';
        if (negative) fixed = fixed.slice(1);
        var parts = fixed.split('.');
        var whole = thousands ? parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousands) : parts[0];
        var out = whole + (decimals > 0 ? '.' + parts[1] : '');
        return negative && parseFloat(fixed) !== 0 ? '-' + out : out;
    }

    function trimZeros(text) {
        return text.replace(/0+$/, '').replace(/\.+$/, '');
    }

    function numberText(number) {
        return trimZeros(numberFormat(number, 1, ''));
    }

    function headroomText(rule) {
        if (rule.headroom_pct === null || rule.headroom_pct === undefined) return '100 %';
        return trimZeros(numberFormat(toFloat(rule.headroom_pct), 2, '')) + ' %';
    }

    function countText(figure, count) {
        var units = COUNT_UNITS[figure] || [figure, figure];
        if (count == 0) return 'no ' + units[1];
        return numberText(count) + ' ' + (count == 1 ? units[0] : units[1]);
    }

    function money(amount) {
        return 'RM ' + numberFormat(toFloat(amount), 2, ',');
    }

    function capacityText(gigabytes) {
        return gigabytes >= 1000 && gigabytes % 1000 === 0 ? (gigabytes / 1000) + ' TB' : gigabytes + ' GB';
    }

    function escapeHtml(value) {
        return toStr(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function makeContext(data) {
        var byId = {};
        data.categories.forEach(function (category) { byId[toInt(category.category_id)] = category; });
        var limits = {};
        data.rules.forEach(function (rule) {
            if (rule.rule_type === 'CAPACITY_CHECK') {
                var key = toStr(rule.attribute_key);
                limits[key] = limits[key] || {};
                limits[key][toInt(rule.category_a)] = true;
            }
        });
        var components = {};
        var candidates = {};
        data.components.forEach(function (part) {
            components[toInt(part.component_id)] = part;
            var categoryId = toInt(part.category_id);
            (candidates[categoryId] = candidates[categoryId] || []).push(part);
        });
        return {
            order: data.categories.map(function (category) { return toInt(category.category_id); }),
            categories: byId,
            rules: data.rules,
            limits: limits,
            components: components,
            candidates: candidates
        };
    }

    function isLimit(context, figure, categoryId) {
        return !!(context.limits[figure] && context.limits[figure][categoryId]);
    }

    function figureTotal(selection, figure, context) {
        var total = 0;
        selection.forEach(function (item, categoryId) {
            if (!isLimit(context, figure, categoryId)) {
                total += toInt(item.component[figure]) * item.quantity;
            }
        });
        return total;
    }

    function readSelection(componentIds, quantities, context) {
        var selection = new Map();
        var problems = [];
        componentIds.forEach(function (pair) {
            var categoryKey = pair[0];
            var componentId = pair[1];
            if (componentId === '') return;
            var category = context.categories[toInt(categoryKey)];
            if (!category || !/^\d+$/.test(String(categoryKey))) {
                problems.push('A part was sent for a category that does not exist.');
                return;
            }
            var name = toStr(category.category_name);
            var component = /^\d+$/.test(componentId) ? context.components[toInt(componentId)] || null : null;
            if (component === null || toInt(component.category_id) !== toInt(categoryKey)) {
                problems.push('The part chosen as ' + name + ' is not one of the ' + name + ' parts.');
                return;
            }
            if (toInt(component.is_active) !== 1) {
                problems.push('“' + component.name + '” is no longer sold.');
                return;
            }
            var quantity = Object.prototype.hasOwnProperty.call(quantities, categoryKey) ? quantities[categoryKey] : '1';
            var max = toInt(category.max_quantity);
            if (typeof quantity !== 'string' || !/^\d+$/.test(quantity) || toInt(quantity) < 1 || toInt(quantity) > max) {
                problems.push(max === 1
                    ? 'Only one ' + name + ' can be chosen.'
                    : 'Choose from 1 to ' + max + ' of the ' + name + '.');
                return;
            }
            selection.set(toInt(categoryKey), { component: component, quantity: toInt(quantity) });
        });
        return [selection, problems];
    }

    function judgeRule(rule, selection, context) {
        var type = toStr(rule.rule_type);
        var key = toStr(rule.attribute_key);
        var aName = toStr(rule.category_a_name);
        var bName = toStr(rule.category_b_name);
        var a = selection.get(toInt(rule.category_a)) || null;
        var b = rule.category_b === null ? null : (selection.get(toInt(rule.category_b)) || null);

        function verdict(status, detail) {
            return {
                id: toInt(rule.rule_id),
                name: toStr(rule.rule_name),
                type: type,
                status: status,
                message: toStr(rule.error_message),
                detail: detail
            };
        }

        if ((RULE_ATTRIBUTES[type] || []).indexOf(key) === -1) {
            return verdict('error', 'This rule cannot be checked: it names “' + key + '”, which a rule of this kind cannot read.');
        }
        var what = ATTRIBUTE_LABELS[key];

        if (type === 'ATTRIBUTE_MATCH') {
            if (a === null || b === null) {
                return verdict('skip', 'Checked once the ' + aName + ' and the ' + bName + ' are chosen.');
            }
            var left = upperTrim(a.component[key]);
            var right = upperTrim(b.component[key]);
            var pairs = [[left, a], [right, b]];
            for (var i = 0; i < pairs.length; i++) {
                if (pairs[i][0] === '') {
                    return verdict('fail', 'The ' + pairs[i][1].component.name + ' has no ' + what + ' recorded, so the match cannot be confirmed.');
                }
            }
            if (left !== right) {
                return verdict('fail', 'The ' + a.component.name + ' is ' + left + '; the ' + b.component.name + ' is ' + right + '.');
            }
            return verdict('pass', 'Both are ' + left + '.');
        }

        if (type === 'CAPACITY_CHECK') {
            if (a === null) {
                return verdict('skip', 'Checked once the ' + aName + ' is chosen.');
            }
            var used = figureTotal(selection, key, context);
            var rated = toInt(a.component[key]) * a.quantity;
            var share = rule.headroom_pct === null || rule.headroom_pct === undefined ? 100 : toFloat(rule.headroom_pct);
            var safe = rated * share / 100;
            var name = a.component.name;
            if (key === 'wattage') {
                var safeText = numberText(safe);
                if (used > safe) {
                    return verdict('fail', 'The parts draw ' + used + ' W; the ' + name + ' can safely supply ' + safeText + ' W (' + headroomText(rule) + ' of ' + rated + ' W).');
                }
                return verdict('pass', 'The parts draw ' + used + ' W of the ' + safeText + ' W the ' + name + ' can safely supply.');
            }
            var room = rule.headroom_pct === null || rule.headroom_pct === undefined
                ? 'has ' + rated
                : 'allows ' + numberText(safe) + ' (' + headroomText(rule) + ' of ' + rated + ')';
            return verdict(used > safe ? 'fail' : 'pass', 'The parts take ' + countText(key, used) + '; the ' + name + ' ' + room + '.');
        }

        if (type === 'REQUIRES_CATEGORY') {
            if (a === null) {
                return verdict('skip', 'Checked once the ' + aName + ' is chosen.');
            }
            var phrases = FLAG_PHRASES[key];
            var flag = a.component[key];
            if (flag !== null && flag !== undefined && toInt(flag) === 1) {
                return verdict('pass', 'The ' + a.component.name + ' ' + phrases[0] + ', so no ' + bName + ' is needed.');
            }
            if (b !== null) {
                return verdict('pass', 'The ' + a.component.name + ' ' + phrases[1] + ', and the ' + b.component.name + ' is included.');
            }
            return verdict('fail', 'The ' + a.component.name + ' ' + phrases[1] + ': add a ' + bName + '.');
        }

        return verdict('error', 'This rule cannot be checked: its kind is unknown.');
    }

    function evaluateSelection(selection, context) {
        var cents = 0;
        var capacity = {};
        var capacityNames = [];
        selection.forEach(function (item, categoryId) {
            var component = item.component;
            var category = context.categories[categoryId];
            cents += phpRound(toFloat(component.price) * 100, 0) * item.quantity;
            if (component.capacity_gb !== null && component.capacity_gb !== undefined && toInt(category.max_quantity) > 1) {
                var name = toStr(category.category_name);
                if (!(name in capacity)) {
                    capacity[name] = 0;
                    capacityNames.push(name);
                }
                capacity[name] += toInt(component.capacity_gb) * item.quantity;
            }
        });

        var verdicts = [];
        context.rules.forEach(function (rule) {
            if (toInt(rule.is_active) === 1) verdicts.push(judgeRule(rule, selection, context));
        });

        var missing = [];
        context.order.forEach(function (categoryId) {
            var category = context.categories[categoryId];
            if (toInt(category.is_required) === 1 && !selection.has(categoryId)) {
                missing.push(toStr(category.category_name));
            }
        });
        var failing = verdicts.some(function (v) { return v.status === 'fail' || v.status === 'error'; });

        return {
            rules: verdicts,
            missing: missing,
            complete: missing.length === 0,
            valid: missing.length === 0 && !failing,
            total_price: Math.floor(cents / 100) + '.' + (cents % 100 < 10 ? '0' : '') + (cents % 100),
            total_wattage: figureTotal(selection, 'wattage', context),
            capacity: capacityNames.length ? capacity : []
        };
    }

    function cloneSelection(selection) {
        return new Map(selection);
    }

    function ruleConcerns(rule, categoryId, context) {
        if (rule.rule_type === 'ATTRIBUTE_MATCH') {
            return toInt(rule.category_a) === categoryId || toInt(rule.category_b) === categoryId;
        }
        return toInt(rule.category_a) === categoryId || !isLimit(context, toStr(rule.attribute_key), categoryId);
    }

    function ruleWaitsFor(rule, selection) {
        var a = toInt(rule.category_a);
        if (rule.rule_type === 'CAPACITY_CHECK') {
            return selection.has(a) ? null : a;
        }
        var b = toInt(rule.category_b);
        if (selection.has(a) === selection.has(b)) return null;
        return selection.has(a) ? b : a;
    }

    function ruleBlocked(rule, selection, candidates, context, except) {
        var status = judgeRule(rule, selection, context).status;
        if (status === 'fail') return true;
        if (status !== 'skip') return false;
        var slot = ruleWaitsFor(rule, selection);
        if (slot === null || slot === except || !candidates[slot] || candidates[slot].length === 0) return false;
        for (var i = 0; i < candidates[slot].length; i++) {
            var ahead = cloneSelection(selection);
            ahead.set(slot, { component: candidates[slot][i], quantity: 1 });
            if (judgeRule(rule, ahead, context).status !== 'fail') return false;
        }
        return true;
    }

    function unfitReason(rule, trial, categoryId, candidates, context) {
        if (!ruleBlocked(rule, trial, candidates, context, null)) return null;
        var key = toStr(rule.attribute_key);
        var what = ATTRIBUTE_LABELS[key];
        var a = toInt(rule.category_a);
        var aName = toStr(rule.category_a_name);
        var mine = trial.get(categoryId).component;
        var waiting = ruleWaitsFor(rule, trial);

        if (rule.rule_type === 'ATTRIBUTE_MATCH') {
            var other = a === categoryId ? toInt(rule.category_b) : a;
            var otherName = toStr(context.categories[other].category_name);
            var value = upperTrim(mine[key]);
            if (value === '') return 'no ' + what + ' recorded';
            if (waiting !== null) return 'no ' + otherName + ' sold has ' + what + ' ' + value;
            var theirs = upperTrim(trial.get(other).component[key]);
            return theirs === ''
                ? 'the ' + otherName + ' has no ' + what + ' recorded'
                : 'its ' + what + ' is ' + value + ', the ' + otherName + '\'s is ' + theirs;
        }

        var used = figureTotal(trial, key, context);
        var wattage = key === 'wattage';
        var amount = function (n) { return wattage ? numberText(n) + ' W' : countText(key, n); };
        if (waiting !== null) {
            return wattage
                ? 'the parts would draw ' + used + ' W, more than any ' + aName + ' sold can safely supply'
                : 'the parts would take ' + countText(key, used) + ', more than any ' + aName + ' sold has';
        }
        var limit = trial.get(a);
        var share = rule.headroom_pct === null || rule.headroom_pct === undefined ? 100 : toFloat(rule.headroom_pct);
        var room = toInt(limit.component[key]) * limit.quantity * share / 100;
        if (a === categoryId) {
            return wattage
                ? 'safely supplies ' + amount(room) + ', the parts draw ' + used + ' W'
                : 'has ' + amount(room) + ', the parts take ' + used;
        }
        return wattage
            ? 'the parts would draw ' + used + ' W, the ' + aName + ' safely supplies ' + amount(room)
            : 'the parts would take ' + countText(key, used) + ', the ' + aName + ' has ' + numberText(room);
    }

    function fitReport(selection, candidates, context) {
        var rules = context.rules.filter(function (rule) {
            return toInt(rule.is_active) === 1
                && (rule.rule_type === 'ATTRIBUTE_MATCH' || rule.rule_type === 'CAPACITY_CHECK')
                && RULE_ATTRIBUTES[rule.rule_type].indexOf(rule.attribute_key) !== -1;
        });

        var report = {};
        context.order.forEach(function (categoryId) {
            var category = context.categories[categoryId];
            if (!candidates[categoryId] || candidates[categoryId].length === 0) return;
            var without = cloneSelection(selection);
            without.delete(categoryId);

            var concerned = [];
            rules.forEach(function (rule) {
                if (ruleConcerns(rule, categoryId, context)) {
                    concerned.push([rule, ruleBlocked(rule, without, candidates, context, categoryId)]);
                }
            });
            if (concerned.length === 0) return;

            candidates[categoryId].forEach(function (part) {
                for (var quantity = 1; quantity <= toInt(category.max_quantity); quantity++) {
                    var trial = cloneSelection(without);
                    trial.set(categoryId, { component: part, quantity: quantity });
                    for (var i = 0; i < concerned.length; i++) {
                        if (concerned[i][1]) continue;
                        var reason = unfitReason(concerned[i][0], trial, categoryId, candidates, context);
                        if (reason !== null) {
                            var byCategory = report[categoryId] = report[categoryId] || {};
                            var byPart = byCategory[toInt(part.component_id)] = byCategory[toInt(part.component_id)] || {};
                            byPart[quantity] = reason;
                            break;
                        }
                    }
                }
            });
        });
        return report;
    }

    function renderVerdicts(result, withTotals) {
        var labels = { pass: 'Passes', fail: 'Fails', skip: 'Skipped', error: 'Broken' };
        var html = '<div class="verdict-summary ' + (result.valid ? 'is-valid' : 'is-invalid') + '">'
            + '<strong>' + (result.valid ? 'Valid: this configuration could be ordered.' : 'Not valid: this configuration could not be ordered yet.') + '</strong>';
        if (result.missing.length) {
            html += ' <span>Still missing: ' + escapeHtml(result.missing.join(', ')) + '.</span>';
        }
        html += '</div>';
        if (withTotals) {
            html += '<dl class="verdict-totals">'
                + '<div><dt>Total price</dt><dd>' + escapeHtml(money(result.total_price)) + '</dd></div>'
                + '<div><dt>Power drawn</dt><dd>' + toInt(result.total_wattage) + ' W</dd></div>';
            Object.keys(Array.isArray(result.capacity) ? {} : result.capacity).forEach(function (name) {
                html += '<div><dt>' + escapeHtml(name) + ' in total</dt><dd>' + escapeHtml(capacityText(toInt(result.capacity[name]))) + '</dd></div>';
            });
            html += '</dl>';
        }
        html += '<ul class="verdicts">';
        result.rules.forEach(function (v) {
            html += '<li class="verdict verdict-' + escapeHtml(v.status) + '" data-rule="' + toInt(v.id) + '">'
                + '<span class="verdict-badge">' + escapeHtml(labels[v.status]) + '</span>'
                + ' <span class="verdict-text">'
                + '<span class="verdict-name">' + escapeHtml(v.name) + '</span>'
                + (v.status === 'fail' ? ' <span class="verdict-message">' + escapeHtml(v.message) + '</span>' : '')
                + ' <span class="verdict-detail">' + escapeHtml(v.detail) + '</span>'
                + '</span></li>';
        });
        if (result.rules.length === 0) {
            html += '<li class="verdict verdict-skip"><span class="verdict-text">No rule is switched on.</span></li>';
        }
        html += '</ul>';
        return html;
    }

    function check(data, componentIds, quantities, view) {
        var context = makeContext(data);
        var read = readSelection(componentIds, quantities, context);
        if (read[1].length) {
            return { status: 422, body: { errors: read[1] } };
        }
        var result = evaluateSelection(read[0], context);
        if (view !== 'configurator') {
            result.html = renderVerdicts(result, true);
            return { status: 200, body: result };
        }
        result.html = renderVerdicts(result, false);
        result.fit = fitReport(read[0], context.candidates, context);
        return { status: 200, body: result };
    }

    var api = { check: check };
    if (typeof module !== 'undefined' && module.exports) module.exports = api;
    else root.PCDemoEngine = api;
})(this);
