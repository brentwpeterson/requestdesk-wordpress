/* RequestDesk Case Study Wizard — Phase A
 *
 * Client-side step navigation. No requests to RequestDesk yet.
 * Phase B replaces the placeholder Generate / Publish buttons with
 * actual API calls.
 */
(function () {
    'use strict';

    var TOTAL_STEPS = 5;

    function init() {
        var wrap = document.querySelector('.rdcs-wizard-wrap');
        if (!wrap) return;

        var stepNumEl = wrap.querySelector('.rdcs-step-num');
        var progressEl = wrap.querySelector('.rdcs-progress');
        var backBtn = wrap.querySelector('.rdcs-back');
        var nextBtn = wrap.querySelector('.rdcs-next');
        var steps = wrap.querySelectorAll('.rdcs-step');
        var formatPanes = wrap.querySelectorAll('.rdcs-format-pane');
        var formatRadios = wrap.querySelectorAll('input[name="rdcs_format"]');
        var formatNote = wrap.querySelector('.rdcs-format-note');

        var currentStep = 1;
        var selectedFormat = null;

        function render() {
            // Show/hide step sections.
            steps.forEach(function (s) {
                s.hidden = parseInt(s.getAttribute('data-step'), 10) !== currentStep;
            });

            // Update step-eyebrow.
            if (stepNumEl) stepNumEl.textContent = String(currentStep);

            // Update progress bar.
            progressEl.setAttribute('data-active-step', String(currentStep));
            var items = progressEl.querySelectorAll('li');
            items.forEach(function (li) {
                var n = parseInt(li.getAttribute('data-step'), 10);
                li.classList.toggle('is-done', n < currentStep);
                li.classList.toggle('is-active', n === currentStep);
            });

            // Nav button states.
            backBtn.disabled = currentStep === 1;
            nextBtn.textContent = currentStep === TOTAL_STEPS ? 'Finish' : 'Next →';
            nextBtn.disabled = currentStep === 1 && !selectedFormat;

            // Reveal the right format pane on Step 2.
            if (currentStep === 2) {
                showFormatPane(selectedFormat);
            }
        }

        function showFormatPane(format) {
            formatPanes.forEach(function (p) {
                p.hidden = p.getAttribute('data-format') !== format;
            });
            if (formatNote) {
                var label = {
                    in_head: 'Structured worksheet',
                    interview: 'Interview transcript',
                    existing_draft: 'Existing draft'
                }[format] || 'Structured worksheet';
                formatNote.textContent = 'Mode: ' + label + '.';
            }
        }

        // Card click — let the label/radio do its thing, then sync state.
        wrap.querySelectorAll('.rdcs-card').forEach(function (card) {
            card.addEventListener('click', function () {
                // Defer to next tick so the radio is checked first.
                setTimeout(syncFormatSelection, 0);
            });
        });
        formatRadios.forEach(function (r) {
            r.addEventListener('change', syncFormatSelection);
        });

        function syncFormatSelection() {
            var checked = wrap.querySelector('input[name="rdcs_format"]:checked');
            selectedFormat = checked ? checked.value : null;
            wrap.querySelectorAll('.rdcs-card').forEach(function (card) {
                var radio = card.querySelector('input[type="radio"]');
                card.classList.toggle('is-selected', !!(radio && radio.checked));
            });
            // If we're on Step 1, just refresh the Next button state.
            if (currentStep === 1) render();
        }

        backBtn.addEventListener('click', function () {
            if (currentStep > 1) {
                currentStep -= 1;
                render();
            }
        });
        nextBtn.addEventListener('click', function () {
            if (currentStep === 1 && !selectedFormat) return;
            if (currentStep < TOTAL_STEPS) {
                currentStep += 1;
                render();
            }
            // Step 5 "Finish" is a Phase A no-op. Phase B wires CPT creation.
        });

        render();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
