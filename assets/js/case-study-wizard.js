/* RequestDesk Case Study Wizard — Phase B
 *
 * Talks to the WP REST proxy at /wp-json/requestdesk/v1/wizard/*. The proxy
 * forwards to the RequestDesk backend (case-study-wizard endpoints) and,
 * on publish, creates a cc_case_study draft locally.
 *
 * Lazy session: a wizard session is created on the first auto-save (not
 * on page load) so opening the wizard page does not produce a dead row in
 * RequestDesk every time.
 */
(function () {
    'use strict';

    var TOTAL_STEPS = 5;
    var AUTOSAVE_DELAY_MS = 800;
    var CFG = window.RDCS_CFG || {};

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
        var partnerSelect = wrap.querySelector('#rdcs-partner');
        var statusEl = wrap.querySelector('.rdcs-status') || createStatusEl(wrap);
        var generateBtn = wrap.querySelector('[data-rdcs-action="generate"]');
        var publishBtn = wrap.querySelector('[data-rdcs-action="publish"]');
        var reviewBox = wrap.querySelector('#rdcs-review');

        var currentStep = 1;
        var selectedFormat = null;
        var sessionId = null;
        var saveTimer = null;
        var inFlight = false;
        var lastGenerated = '';

        function payload() {
            return {
                input_mode: selectedFormat,
                partner_id: partnerSelect ? parseInt(partnerSelect.value, 10) || 0 : 0,
                client_name: val('#rdcs-client-name'),
                challenge: val('#rdcs-challenge'),
                solution:  val('#rdcs-solution'),
                results:   val('#rdcs-results'),
                metrics:   val('#rdcs-metrics'),
                quote:     val('#rdcs-quote'),
                raw_input: rawInputForFormat()
            };
        }

        function val(sel) {
            var el = wrap.querySelector(sel);
            return el ? (el.value || '').trim() : '';
        }

        function rawInputForFormat() {
            if (selectedFormat === 'interview') return val('#rdcs-transcript');
            if (selectedFormat === 'existing_draft') return val('#rdcs-draft');
            return '';
        }

        function render() {
            steps.forEach(function (s) {
                s.hidden = parseInt(s.getAttribute('data-step'), 10) !== currentStep;
            });
            if (stepNumEl) stepNumEl.textContent = String(currentStep);
            progressEl.setAttribute('data-active-step', String(currentStep));
            var items = progressEl.querySelectorAll('li');
            items.forEach(function (li) {
                var n = parseInt(li.getAttribute('data-step'), 10);
                li.classList.toggle('is-done', n < currentStep);
                li.classList.toggle('is-active', n === currentStep);
            });
            backBtn.disabled = currentStep === 1;
            if (currentStep === TOTAL_STEPS) {
                nextBtn.hidden = true;
            } else {
                nextBtn.hidden = false;
                nextBtn.textContent = 'Next →';
                nextBtn.disabled = currentStep === 1 && !selectedFormat;
            }
            if (currentStep === 2) showFormatPane(selectedFormat);
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

        function setStatus(msg, kind) {
            statusEl.textContent = msg || '';
            statusEl.dataset.kind = kind || '';
        }

        // --- Network layer --------------------------------------------------

        function api(method, path, body) {
            if (!CFG.restUrl || !CFG.nonce) {
                return Promise.reject(new Error('Wizard not configured (missing REST URL or nonce)'));
            }
            var url = CFG.restUrl.replace(/\/$/, '') + path;
            return fetch(url, {
                method: method,
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': CFG.nonce
                },
                body: body ? JSON.stringify(body) : null
            }).then(function (r) {
                return r.json().then(function (data) {
                    if (!r.ok) {
                        var msg = (data && (data.message || data.detail)) || ('HTTP ' + r.status);
                        throw new Error(msg);
                    }
                    return data;
                });
            });
        }

        function ensureSession() {
            if (sessionId) return Promise.resolve(sessionId);
            return api('POST', '/wizard/sessions', payload()).then(function (resp) {
                var cs = resp && resp.case_study;
                sessionId = cs && (cs.id || cs._id || cs.case_study_id) || null;
                if (!sessionId) throw new Error('RequestDesk did not return a session id');
                return sessionId;
            });
        }

        // --- Auto-save ------------------------------------------------------

        function scheduleAutoSave() {
            if (saveTimer) clearTimeout(saveTimer);
            saveTimer = setTimeout(autoSave, AUTOSAVE_DELAY_MS);
        }

        function autoSave() {
            if (inFlight) {
                scheduleAutoSave();
                return;
            }
            inFlight = true;
            setStatus('Saving…', 'busy');
            ensureSession()
                .then(function (id) {
                    return api('PUT', '/wizard/sessions/' + encodeURIComponent(id), payload());
                })
                .then(function () {
                    setStatus('Saved', 'ok');
                })
                .catch(function (err) {
                    setStatus('Save failed: ' + err.message, 'err');
                })
                .then(function () { inFlight = false; });
        }

        // --- Generate -------------------------------------------------------

        function doGenerate() {
            if (!sessionId) {
                setStatus('Save your inputs first.', 'err');
                autoSave();
                return;
            }
            generateBtn.disabled = true;
            setStatus('Generating draft (may take 30-60s)…', 'busy');
            api('POST', '/wizard/sessions/' + encodeURIComponent(sessionId) + '/generate', { regenerate: false })
                .then(function (resp) {
                    var content = resp && resp.content;
                    if (!content) throw new Error('No content returned');
                    lastGenerated = content;
                    if (reviewBox) reviewBox.value = stripHtml(content);
                    setStatus('Draft generated. Move to Step 4 to review.', 'ok');
                    currentStep = 4;
                    render();
                })
                .catch(function (err) {
                    setStatus('Generate failed: ' + err.message, 'err');
                })
                .then(function () { generateBtn.disabled = false; });
        }

        function stripHtml(html) {
            var div = document.createElement('div');
            div.innerHTML = html;
            return (div.textContent || div.innerText || '').trim();
        }

        // --- Publish --------------------------------------------------------

        function doPublish() {
            if (!sessionId) {
                setStatus('No session to publish.', 'err');
                return;
            }
            publishBtn.disabled = true;
            setStatus('Creating draft post…', 'busy');
            var body = payload();
            body.content = reviewBox ? reviewBox.value : lastGenerated;
            body.post_title = val('#rdcs-client-name') || 'Case Study Draft';
            api('POST', '/wizard/sessions/' + encodeURIComponent(sessionId) + '/publish', body)
                .then(function (resp) {
                    if (!resp || !resp.success) throw new Error('Publish returned no success flag');
                    setStatus('Draft created. Opening editor…', 'ok');
                    if (resp.edit_url) {
                        window.location.href = resp.edit_url;
                    }
                })
                .catch(function (err) {
                    setStatus('Publish failed: ' + err.message, 'err');
                    publishBtn.disabled = false;
                });
        }

        // --- Wire up --------------------------------------------------------

        wrap.querySelectorAll('.rdcs-card').forEach(function (card) {
            card.addEventListener('click', function () {
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
            if (currentStep === 1) render();
            scheduleAutoSave();
        }

        if (partnerSelect) {
            partnerSelect.addEventListener('change', scheduleAutoSave);
        }

        wrap.querySelectorAll('#rdcs-client-name, #rdcs-challenge, #rdcs-solution, #rdcs-results, #rdcs-metrics, #rdcs-quote, #rdcs-transcript, #rdcs-draft').forEach(function (el) {
            el.addEventListener('input', scheduleAutoSave);
        });

        if (generateBtn) generateBtn.addEventListener('click', doGenerate);
        if (publishBtn) publishBtn.addEventListener('click', doPublish);

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
        });

        render();
    }

    function createStatusEl(wrap) {
        var el = document.createElement('div');
        el.className = 'rdcs-status';
        var nav = wrap.querySelector('.rdcs-nav');
        if (nav) nav.parentNode.insertBefore(el, nav);
        else wrap.appendChild(el);
        return el;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
