/**
 * Homepage Hero Terminal Animation + Slot Carousel
 *
 * Reads configuration from window.requestdeskHeroTerminal (set via wp_localize_script).
 * - Animates terminal typing for the active sequence.
 * - Exposes window.requestdeskHeroCarousel with goTo()/pause()/resume() methods.
 * - Dispatches `rd-hero-slot-change` events so the inline PHP rotator can
 *   update headline + CTA + nav-dot + video visibility in lockstep.
 *
 * @package RequestDesk
 * @version 1.1.0
 */
(function() {
    'use strict';

    var config = window.requestdeskHeroTerminal;
    if (!config || !config.sequences || !config.sequences.length) {
        return;
    }

    var SEQUENCES = config.sequences;
    var HOLD_TIMES = Array.isArray(config.holdTimes) ? config.holdTimes : [];
    var TYPE_SPEED_MIN = parseInt(config.typeSpeedMin, 10) || 45;
    var TYPE_SPEED_MAX = parseInt(config.typeSpeedMax, 10) || 95;
    var FADE_DURATION = 300;
    var DEFAULT_HOLD = 2000;

    var containerId = config.containerId || '';
    var container = containerId ? document.getElementById(containerId) : document;
    if (!container) return;

    var output = container.querySelector('.rd-hero-terminal-output');
    var cursor = container.querySelector('.rd-hero-terminal-cursor');
    var content = container.querySelector('.rd-hero-terminal-content');

    var currentSequence = 0;
    var paused = false;
    var resumeTimer = null;
    var advanceTimer = null;
    var prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function dispatchSlotChange(index) {
        try {
            document.dispatchEvent(new CustomEvent('rd-hero-slot-change', {
                detail: { slot: index }
            }));
        } catch (err) {
            // CustomEvent constructor unsupported (very old IE) — silently skip.
        }
    }

    function holdFor(index) {
        var t = HOLD_TIMES[index];
        return (typeof t === 'number' && t > 0) ? t : DEFAULT_HOLD;
    }

    // Public carousel API used by the inline PHP rotator and the YT integration.
    window.requestdeskHeroCarousel = {
        goTo: function(target) {
            target = parseInt(target, 10);
            if (isNaN(target)) return;
            target = ((target % SEQUENCES.length) + SEQUENCES.length) % SEQUENCES.length;
            if (target === currentSequence) return;
            clearTimeout(advanceTimer);
            currentSequence = target;
            dispatchSlotChange(currentSequence);
            restartTerminal();
        },
        pause: function() {
            paused = true;
            clearTimeout(advanceTimer);
            clearTimeout(resumeTimer);
        },
        resume: function(delay) {
            paused = false;
            clearTimeout(resumeTimer);
            var ms = (typeof delay === 'number' && delay > 0) ? delay : 0;
            resumeTimer = setTimeout(scheduleAdvance, ms);
        }
    };

    if (!output || !cursor || !content) {
        // No terminal in the DOM (e.g. video-only slot) — still expose the API
        // and dispatch the initial slot so the rotator wires up.
        dispatchSlotChange(currentSequence);
        scheduleAdvance();
        return;
    }

    if (prefersReducedMotion) {
        var lines = SEQUENCES[0] || [];
        output.textContent = lines.map(function(line) { return line.text; }).join('');
        cursor.classList.remove('blinking');
        dispatchSlotChange(0);
        return;
    }

    function getRandomTypeSpeed() {
        return Math.floor(Math.random() * (TYPE_SPEED_MAX - TYPE_SPEED_MIN + 1)) + TYPE_SPEED_MIN;
    }

    function typeText(text, callback) {
        var index = 0;
        cursor.classList.remove('blinking');
        function typeChar() {
            if (index < text.length) {
                output.textContent += text.charAt(index);
                index++;
                setTimeout(typeChar, getRandomTypeSpeed());
            } else {
                cursor.classList.add('blinking');
                if (callback) callback();
            }
        }
        typeChar();
    }

    function runSequence(onComplete) {
        var LINES = SEQUENCES[currentSequence] || [];
        var lineIndex = 0;
        content.style.opacity = '1';
        output.textContent = '';

        function nextLine() {
            if (lineIndex < LINES.length) {
                var line = LINES[lineIndex];
                typeText(line.text, function() {
                    lineIndex++;
                    setTimeout(nextLine, line.delay || 0);
                });
            } else {
                cursor.classList.add('blinking');
                if (onComplete) onComplete();
            }
        }

        nextLine();
    }

    function scheduleAdvance() {
        if (paused) return;
        clearTimeout(advanceTimer);
        advanceTimer = setTimeout(function() {
            content.classList.add('fading');
            setTimeout(function() {
                content.classList.remove('fading');
                content.style.opacity = '1';
                currentSequence = (currentSequence + 1) % SEQUENCES.length;
                dispatchSlotChange(currentSequence);
                restartTerminal();
            }, FADE_DURATION);
        }, holdFor(currentSequence));
    }

    function restartTerminal() {
        runSequence(scheduleAdvance);
    }

    dispatchSlotChange(currentSequence);
    runSequence(scheduleAdvance);
})();
