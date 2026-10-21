/**
 * Live SMS length counter for textareas marked with data-sms-counter.
 *
 * Counts the way smsoffice.ge bills (checked against https://smsoffice.ge/analyzer/):
 *  - text made only of GSM characters: 160 in one SMS, 153 per SMS after that;
 *  - one other character (Georgian, emoji, “ ” — …) switches the whole text
 *    to 70 in one SMS, 67 per SMS after that;
 *  - ^ { } \ [ ] ~ | € take two places in either case, an emoji usually two;
 *  - a line break takes two: the form submits it as \r\n and that is sent.
 * smsoffice.ge differs from plain GSM 03.38 in two places: § is not GSM, ` is.
 *
 * Placeholders are counted at the length of the value sent in their place
 * (data-sms-counter carries those samples), not as "{name}".
 */
(function (root) {
    'use strict';

    var GSM = '@£$¥èéùìòÇ\n\r ØøÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !"#¤%&\'()*+,-./0123456789:;<=>?'
        + '¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ`¿abcdefghijklmnopqrstuvwxyzäöñüà';
    var DOUBLE = '^{}\\[]~|€';

    /**
     * @return {{units: number, parts: number, unicode: boolean, left: number}}
     *   units: places the text takes; parts: SMS billed; left: places still
     *   free before one more SMS is needed.
     */
    function count(text) {
        text = String(text).replace(/\r\n|\r|\n/g, '\r\n').trim();

        var units = 0;
        var unicode = false;

        for (var ch of text) {
            if (DOUBLE.indexOf(ch) !== -1) {
                units += 2;
                continue;
            }

            if (GSM.indexOf(ch) === -1) {
                unicode = true;
            }

            // UTF-16 length: an emoji outside the BMP takes two places.
            units += ch.length;
        }

        var single = unicode ? 70 : 160;
        var part = unicode ? 67 : 153;
        var parts = units === 0 ? 0 : (units <= single ? 1 : Math.ceil(units / part));
        var capacity = parts <= 1 ? single : parts * part;

        return { units: units, parts: parts, unicode: unicode, left: capacity - units };
    }

    function fill(text, samples) {
        return String(text).replace(/\{([A-Za-z_][A-Za-z0-9_]*)\}/g, function (match, name) {
            return Object.prototype.hasOwnProperty.call(samples, name) ? samples[name] : match;
        });
    }

    function format(template, values) {
        return template.replace(/:([a-z]+)/g, function (match, key) {
            return Object.prototype.hasOwnProperty.call(values, key) ? values[key] : match;
        });
    }

    function escapeHtml(text) {
        return String(text).replace(/[&<>"']/g, function (ch) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch];
        });
    }

    function render(textarea, output, config) {
        var samples = config.samples || {};
        var labels = config.labels || {};
        var approx = config.approx || [];
        var result = count(fill(textarea.value, samples));
        var html = '<strong>' + escapeHtml(format(labels.summary, { chars: result.units, sms: result.parts })) + '</strong>';

        if (result.parts > 0) {
            html += ' · ' + escapeHtml(format(labels.left, { left: result.left, sms: result.parts }));
        }

        html += '<div class="text-muted">' + escapeHtml(result.unicode ? labels.unicode : labels.latin)
            + ' ' + escapeHtml(labels.doubles) + '</div>';

        var used = [];
        var seen = {};
        textarea.value.replace(/\{([A-Za-z_][A-Za-z0-9_]*)\}/g, function (match, name) {
            if (!seen[name] && Object.prototype.hasOwnProperty.call(samples, name)) {
                seen[name] = true;
                used.push('{' + name + '} ' + (approx.indexOf(name) !== -1 ? '≈ ' : '= ') + count(samples[name]).units);
            }

            return match;
        });

        if (used.length > 0) {
            html += '<div class="text-muted">' + escapeHtml(format(labels.samples, { list: used.join(', ') })) + '</div>';
        }

        output.innerHTML = html;
    }

    function attach(textarea) {
        if (textarea.dataset.smsCounterReady) {
            return;
        }

        var output = textarea.parentNode.querySelector('[data-sms-counter-output]');

        if (!output) {
            return;
        }

        var config;

        try {
            config = JSON.parse(textarea.dataset.smsCounter || '{}');
        } catch (e) {
            return;
        }

        textarea.dataset.smsCounterReady = '1';
        textarea.addEventListener('input', function () {
            render(textarea, output, config);
        });
        render(textarea, output, config);
    }

    function init() {
        document.querySelectorAll('textarea[data-sms-counter]').forEach(attach);
    }

    root.SmsCounter = { count: count, fill: fill, init: init };

    if (typeof document !== 'undefined') {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
    }
})(typeof window !== 'undefined' ? window : globalThis);
