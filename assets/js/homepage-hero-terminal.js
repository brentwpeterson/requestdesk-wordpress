/**
 * Homepage Hero Terminal Animation
 *
 * Reads configuration from window.requestdeskHeroTerminal (set via wp_localize_script).
 * Supports multiple sequences that rotate on each cycle.
 *
 * @package RequestDesk
 * @version 1.0.0
 */
(function() {
    'use strict';

    var config = window.requestdeskHeroTerminal;
    if (!config || !config.sequences || !config.sequences.length) {
        return;
    }

    var SEQUENCES = config.sequences;
    var TYPE_SPEED_MIN = parseInt(config.typeSpeedMin, 10) || 45;
    var TYPE_SPEED_MAX = parseInt(config.typeSpeedMax, 10) || 95;
    var FADE_DURATION = 300;

    var containerId = config.containerId || '';
    var container = containerId ? document.getElementById(containerId) : document;
    if (!container) {
        return;
    }

    var output = container.querySelector('.rd-hero-terminal-output');
    var cursor = container.querySelector('.rd-hero-terminal-cursor');
    var content = container.querySelector('.rd-hero-terminal-content');

    if (!output || !cursor || !content) {
        return;
    }

    var currentSequence = 0;
    var LINES = SEQUENCES[0];

    var prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (prefersReducedMotion) {
        var finalText = LINES.map(function(line) { return line.text; }).join('');
        output.textContent = finalText;
        cursor.classList.remove('blinking');
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

    function runSequence() {
        var lineIndex = 0;
        content.style.opacity = '1';
        output.textContent = '';

        function nextLine() {
            if (lineIndex < LINES.length) {
                var line = LINES[lineIndex];
                typeText(line.text, function() {
                    lineIndex++;
                    setTimeout(nextLine, line.delay);
                });
            } else {
                cursor.classList.add('blinking');
                setTimeout(fadeAndRestart, 1500);
            }
        }

        nextLine();
    }

    function fadeAndRestart() {
        content.classList.add('fading');
        setTimeout(function() {
            content.classList.remove('fading');
            content.style.opacity = '1';
            currentSequence = (currentSequence + 1) % SEQUENCES.length;
            LINES = SEQUENCES[currentSequence];
            runSequence();
        }, FADE_DURATION);
    }

    runSequence();
})();
