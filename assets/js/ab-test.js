/**
 * RequestDesk A/B Test — swap and count.
 *
 * Runs after the inline boot script in <head> (class-requestdesk-ab-test.php)
 * has put the visitor in a bucket. For Variant B it replaces the inner HTML of
 * each configured selector. Then it counts the view (once per browser session)
 * and the goal (once per visitor) and pushes both to window.dataLayer.
 *
 * A forced variant (?rd_ab=a|b) swaps but never counts.
 */
(function () {
    'use strict';

    var cfg = window.rdAbTestConfig;
    var state = window.rdAbTest;
    var root = document.documentElement;

    if (!cfg || !state) {
        if (window.console) { // hardcode-ok: window.console, not a URL
            console.error('[rd-ab-test] missing config or boot state; nothing swapped or counted.');
        }
        root.setAttribute('data-rd-ab-ready', 'error');
        return;
    }

    function swap() {
        if (state.variant !== 'b') {
            return;
        }
        cfg.changes.forEach(function (change) {
            var nodes = document.querySelectorAll(change.selector);
            if (!nodes.length && window.console) { // hardcode-ok: window.console, not a URL
                console.error('[rd-ab-test] selector matched nothing: ' + change.selector);
            }
            Array.prototype.forEach.call(nodes, function (el) {
                el.innerHTML = change.html;
            });
        });
    }

    function send(type) {
        var payload = { test: state.test, variant: state.variant, type: type };

        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({
            event: type === 'goal' ? 'rd_ab_goal' : 'rd_ab_view',
            rd_ab_test: state.test,
            rd_ab_variant: state.variant
        });

        var body = JSON.stringify(payload);
        if (navigator.sendBeacon) {
            var blob = new Blob([body], { type: 'application/json' });
            if (navigator.sendBeacon(cfg.eventUrl, blob)) {
                return;
            }
        }
        fetch(cfg.eventUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: body,
            keepalive: true,
            credentials: 'omit'
        }).catch(function (err) {
            if (window.console) { // hardcode-ok: window.console, not a URL
                console.error('[rd-ab-test] could not record ' + type + ': ' + err);
            }
        });
    }

    function storageFlag(store, key) {
        try {
            if (store.getItem(key)) {
                return true;
            }
            store.setItem(key, '1');
            return false;
        } catch (e) {
            // Storage blocked (private mode). Count anyway; a repeat is
            // better than losing the visitor.
            return false;
        }
    }

    var goalCounted = false;
    function goal() {
        if (state.forced || goalCounted) {
            return;
        }
        goalCounted = true;
        if (storageFlag(window.localStorage, 'rd_ab_goal_' + state.test)) {
            return;
        }
        send('goal');
    }

    function watchGoals() {
        if (cfg.goalSelector) {
            document.addEventListener('click', function (e) {
                if (e.target.closest && e.target.closest(cfg.goalSelector)) {
                    goal();
                }
            }, true);
        }
        if (cfg.goalHubspot) {
            // Legacy embed (v2) posts a message; the developer embed fires a
            // window event. Listen for both.
            window.addEventListener('message', function (e) {
                if (e.data && e.data.type === 'hsFormCallback' && e.data.eventName === 'onFormSubmitted') {
                    goal();
                }
            });
            window.addEventListener('hs-form-event:on-submission:success', goal);
        }
    }

    function run() {
        swap();
        root.setAttribute('data-rd-ab-ready', '1');

        if (!state.forced && !storageFlag(window.sessionStorage, 'rd_ab_view_' + state.test)) {
            send('view');
        }
        watchGoals();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }
})();
