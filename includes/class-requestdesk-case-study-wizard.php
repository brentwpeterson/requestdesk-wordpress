<?php
/**
 * RequestDesk Case Study Wizard (Phase A — UI shell only).
 *
 * Registers a "Create with Wizard" submenu under Case Studies when the
 * `enable_case_study_wizard` setting is on, and renders a 5-step wizard:
 *   1. Format choice (in-head / interview transcript / existing draft)
 *   2. Data gathering
 *   3. Generate
 *   4. Review
 *   5. Publish
 *
 * Phase A is UI-only. Step navigation is client-side. No requests to
 * RequestDesk yet — those land in Phase B alongside CPT publish.
 */

if (!defined('ABSPATH')) {
    exit;
}

class RequestDesk_Case_Study_Wizard {

    const MENU_SLUG = 'requestdesk-case-study-wizard';

    public function __construct() {
        if (!RequestDesk_Case_Study::wizard_enabled()) {
            return;
        }
        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    public function register_menu() {
        add_submenu_page(
            'edit.php?post_type=cc_case_study',
            'Create with Wizard',
            'Create with Wizard',
            'edit_posts',
            self::MENU_SLUG,
            array($this, 'render_page')
        );
    }

    public function enqueue_assets($hook) {
        // Only load on the wizard page itself.
        if (strpos($hook, self::MENU_SLUG) === false) {
            return;
        }
        wp_enqueue_style(
            'requestdesk-case-study-wizard',
            REQUESTDESK_PLUGIN_URL . 'assets/css/case-study-wizard.css',
            array(),
            REQUESTDESK_VERSION
        );
        wp_enqueue_script(
            'requestdesk-case-study-wizard',
            REQUESTDESK_PLUGIN_URL . 'assets/js/case-study-wizard.js',
            array(),
            REQUESTDESK_VERSION,
            true
        );
        wp_localize_script(
            'requestdesk-case-study-wizard',
            'RDCS_CFG',
            array(
                'restUrl' => esc_url_raw(rest_url('requestdesk/v1')),
                'nonce'   => wp_create_nonce('wp_rest'),
            )
        );
    }

    public function render_page() {
        ?>
        <div class="wrap rdcs-wizard-wrap">
            <div class="rdcs-header">
                <p class="rdcs-eyebrow">A guided wizard for RequestDesk Connector</p>
                <h1>Write a case study using AI</h1>
            </div>

            <ol class="rdcs-progress" data-active-step="1">
                <li data-step="1"><span class="rdcs-dot"></span><span class="rdcs-label">Step 1: Information format</span></li>
                <li data-step="2"><span class="rdcs-dot"></span><span class="rdcs-label">Step 2: Gather the story</span></li>
                <li data-step="3"><span class="rdcs-dot"></span><span class="rdcs-label">Step 3: Generate draft</span></li>
                <li data-step="4"><span class="rdcs-dot"></span><span class="rdcs-label">Step 4: Review &amp; edit</span></li>
                <li data-step="5"><span class="rdcs-dot"></span><span class="rdcs-label">Step 5: Publish</span></li>
            </ol>

            <div class="rdcs-step-eyebrow">STEP <span class="rdcs-step-num">1</span></div>

            <!-- ============================================================ -->
            <!-- STEP 1: Information format                                    -->
            <!-- ============================================================ -->
            <section class="rdcs-step" data-step="1">
                <h2>Start the case study</h2>

                <?php
                $partners = get_posts(array(
                    'post_type'      => 'cc_partner',
                    'post_status'    => 'publish',
                    'posts_per_page' => -1,
                    'orderby'        => 'title',
                    'order'          => 'ASC',
                ));
                ?>
                <table class="form-table rdcs-partner-row">
                    <tr>
                        <th><label for="rdcs-partner">Connect a partner</label></th>
                        <td>
                            <select id="rdcs-partner" name="rdcs_partner">
                                <option value="0">&mdash; No partner / client only &mdash;</option>
                                <?php foreach ($partners as $partner) : ?>
                                    <option value="<?php echo (int) $partner->ID; ?>"><?php echo esc_html($partner->post_title); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php if (empty($partners)) : ?>
                                    No partners published yet. <a href="<?php echo esc_url(admin_url('post-new.php?post_type=cc_partner')); ?>">Add one</a> if this case study was delivered with a partner.
                                <?php else : ?>
                                    If this case study was delivered with a partner from your Partners directory, pick them here. Optional.
                                <?php endif; ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <hr class="rdcs-divider">

                <p class="rdcs-subhead">The information I have about this client lives:</p>

                <div class="rdcs-card-grid">
                    <label class="rdcs-card">
                        <input type="radio" name="rdcs_format" value="in_head">
                        <span class="rdcs-card-icon">🗒️</span>
                        <span class="rdcs-card-title">In my head</span>
                        <span class="rdcs-card-desc">I'll use a structured worksheet to organize my thoughts.</span>
                    </label>

                    <label class="rdcs-card">
                        <input type="radio" name="rdcs_format" value="interview">
                        <span class="rdcs-card-icon">🎙️</span>
                        <span class="rdcs-card-title">In an interview transcript</span>
                        <span class="rdcs-card-desc">I have a recorded conversation with the client. I'll paste it in.</span>
                    </label>

                    <label class="rdcs-card">
                        <input type="radio" name="rdcs_format" value="existing_draft">
                        <span class="rdcs-card-icon">📄</span>
                        <span class="rdcs-card-title">In an existing draft</span>
                        <span class="rdcs-card-desc">I already have a draft that needs revising.</span>
                    </label>
                </div>
            </section>

            <!-- ============================================================ -->
            <!-- STEP 2: Gather the story                                      -->
            <!-- ============================================================ -->
            <section class="rdcs-step" data-step="2" hidden>
                <h2>Gather the story</h2>
                <p class="rdcs-subhead rdcs-format-note">The form below adapts to the format you chose.</p>

                <div class="rdcs-format-pane" data-format="in_head">
                    <table class="form-table">
                        <tr>
                            <th><label for="rdcs-client-name">Client name</label></th>
                            <td><input id="rdcs-client-name" type="text" class="regular-text" placeholder="Acme Co."></td>
                        </tr>
                        <tr>
                            <th><label for="rdcs-challenge">Describe the challenge</label></th>
                            <td><textarea id="rdcs-challenge" rows="4" class="large-text" placeholder="What was the customer struggling with?"></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="rdcs-solution">Describe the solution</label></th>
                            <td><textarea id="rdcs-solution" rows="4" class="large-text" placeholder="What did we do for them?"></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="rdcs-results">Describe the results</label></th>
                            <td><textarea id="rdcs-results" rows="4" class="large-text" placeholder="Business outcomes, growth metrics, qualitative wins..."></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="rdcs-metrics">1–3 quantifiable metrics</label></th>
                            <td><textarea id="rdcs-metrics" rows="3" class="large-text" placeholder="Examples: 45% faster launch, 3x conversion lift, 15% AOV increase"></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="rdcs-quote">Direct quote from the client</label></th>
                            <td><textarea id="rdcs-quote" rows="3" class="large-text" placeholder="Exact words, with attribution"></textarea></td>
                        </tr>
                    </table>
                </div>

                <div class="rdcs-format-pane" data-format="interview" hidden>
                    <p class="description">Paste your interview transcript below. RequestDesk will extract the case-study structure from it.</p>
                    <textarea id="rdcs-transcript" rows="18" class="large-text code" placeholder="[00:00] Client: When we first started..."></textarea>
                </div>

                <div class="rdcs-format-pane" data-format="existing_draft" hidden>
                    <p class="description">Paste your existing draft. RequestDesk will rewrite and tighten it.</p>
                    <textarea id="rdcs-draft" rows="18" class="large-text" placeholder="Paste your draft here..."></textarea>
                </div>
            </section>

            <!-- ============================================================ -->
            <!-- STEP 3: Generate draft                                        -->
            <!-- ============================================================ -->
            <section class="rdcs-step" data-step="3" hidden>
                <h2>Generate draft</h2>
                <p class="rdcs-subhead">Send your information to RequestDesk and produce a first draft. Generation takes 30 to 60 seconds.</p>
                <button type="button" class="button button-primary button-large" data-rdcs-action="generate">Generate draft</button>
                <p class="description">The result lands in Step 4 for review and edit before you publish.</p>
            </section>

            <!-- ============================================================ -->
            <!-- STEP 4: Review                                                -->
            <!-- ============================================================ -->
            <section class="rdcs-step" data-step="4" hidden>
                <h2>Review &amp; edit</h2>
                <p class="rdcs-subhead">Tighten the draft. Anything you change here is what gets published.</p>
                <textarea id="rdcs-review" rows="20" class="large-text" placeholder="(Generated content will appear here in Phase B)"></textarea>
            </section>

            <!-- ============================================================ -->
            <!-- STEP 5: Publish                                               -->
            <!-- ============================================================ -->
            <section class="rdcs-step" data-step="5" hidden>
                <h2>Publish</h2>
                <p class="rdcs-subhead">Save the final case study as a draft post under <em>Case Studies</em>. You will land on the standard WordPress editor for final review, image selection, and publishing.</p>
                <button type="button" class="button button-primary button-large" data-rdcs-action="publish">Create draft and open editor</button>
            </section>

            <!-- ============================================================ -->
            <!-- Navigation                                                    -->
            <!-- ============================================================ -->
            <div class="rdcs-nav">
                <button type="button" class="button rdcs-back" disabled>&larr; Back</button>
                <button type="button" class="button button-primary rdcs-next">Next &rarr;</button>
            </div>
        </div>
        <?php
    }
}

new RequestDesk_Case_Study_Wizard();
