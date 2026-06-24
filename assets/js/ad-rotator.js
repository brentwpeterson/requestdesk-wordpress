/**
 * RequestDesk Random Ad rotator.
 *
 * Picks random ad(s) from the localized pools (window.RD_ADS.pools, bucketed by
 * placement: banner / sidebar / all) and fills every [data-rd-ad] slot on load.
 * Because the pick happens in the browser, the ad rotates on every page view
 * even when the page HTML is cached.
 *
 * Each slot's data-placement chooses the bucket; data-count controls how many
 * ads it shows (no repeats until that bucket is exhausted).
 */
(function () {
    'use strict';

    function ready(fn) {
        if (document.readyState !== 'loading') {
            fn();
        } else {
            document.addEventListener('DOMContentLoaded', fn);
        }
    }

    // Fisher-Yates shuffle on a copy.
    function shuffle(arr) {
        var a = arr.slice();
        for (var i = a.length - 1; i > 0; i--) {
            var j = Math.floor(Math.random() * (i + 1));
            var t = a[i]; a[i] = a[j]; a[j] = t;
        }
        return a;
    }

    ready(function () {
        var data = window.RD_ADS || {};
        var pools = data.pools || {};

        var shown = [];
        var slots = document.querySelectorAll('[data-rd-ad]');
        Array.prototype.forEach.call(slots, function (slot) {
            var key = slot.getAttribute('data-placement') || 'all';
            var pool = Array.isArray(pools[key]) ? pools[key] : (Array.isArray(pools.all) ? pools.all : []);
            if (!pool.length) {
                return;
            }
            var count = parseInt(slot.getAttribute('data-count'), 10) || 1;
            count = Math.max(1, Math.min(count, pool.length));

            var picks = shuffle(pool).slice(0, count);
            slot.innerHTML = picks.join('');

            // Collect the ids actually rendered for the impression beacon.
            var ads = slot.querySelectorAll('[data-rd-ad-id]');
            Array.prototype.forEach.call(ads, function (el) {
                var id = el.getAttribute('data-rd-ad-id');
                if (id) { shown.push(id); }
            });
        });

        // Fire one impression beacon for everything shown this load.
        if (shown.length && data.ajaxUrl && navigator.sendBeacon) {
            try {
                var fd = new FormData();
                fd.append('action', 'requestdesk_ad_impression');
                shown.forEach(function (id) { fd.append('ids[]', id); });
                navigator.sendBeacon(data.ajaxUrl, fd);
            } catch (e) { /* impressions are best-effort */ }
        }
    });
})();
