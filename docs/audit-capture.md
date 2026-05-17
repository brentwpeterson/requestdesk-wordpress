# Audit Capture (newsletter → WP)

Captures audit requests from newsletter button clicks with as little friction as
possible. Subscriber email already lives in the URL, so they never refill a
form. Every request is logged as a `cc_audit_request` CPT post and an email
goes to the configured notification address for manual fulfillment.

## Flow

```
Newsletter (HubSpot)
├── Has company domain      → button URL has ?em=...&dom=...
│                             → click lands on /audit/, request logged, confirmation shown
└── No company domain       → button URL has ?em=... only
                              → click lands on /audit/, one-field URL form shown
                              → submit logs request, confirmation shown
```

## WordPress setup (one-time)

1. Create a page at slug `/audit/` (title: "Your Content Cucumber audit" or similar).
2. Drop the shortcode into the page body:

   ```
   [cc_audit_landing]
   ```

3. Go to **Audit Requests → Settings** in WP admin and set the notification email.

## Optional shortcode attributes

```
[cc_audit_landing
   heading="Your audit"
   submit_label="Run my audit"
   confirm_message="We're auditing %s. Report in ~5 minutes."]
```

`%s` in `confirm_message` is replaced with the audited URL.

## HubSpot email template (smart content)

In the HubSpot email template, use smart content keyed on whether the contact
has a company domain.

### Has-domain button (one-click)

```html
<a href="https://contentcucumber.com/audit/?em={{ contact.email|urlencode }}&dom={{ contact.company|urlencode }}"
   style="background:#58c558;color:#000;padding:14px 28px;border-radius:6px;
          font-weight:600;text-decoration:none;display:inline-block;">
  Get my audit
</a>
```

### No-domain button (one-field form)

```html
<a href="https://contentcucumber.com/audit/?em={{ contact.email|urlencode }}"
   style="background:#58c558;color:#000;padding:14px 28px;border-radius:6px;
          font-weight:600;text-decoration:none;display:inline-block;">
  Tell us what to audit
</a>
```

### Smart content rule

In HubSpot email editor:

1. Add a smart rule on the button block.
2. Rule: "If `Company name` (or `Website`) is known → show has-domain button.
   Else → show no-domain button."
3. Both buttons point to the same `/audit/` page; only the query string differs.

## REST endpoint (programmatic / AJAX)

```
POST https://contentcucumber.com/wp-json/cc-audit/v1/request
Content-Type: application/json

{ "email": "subscriber@example.com",
  "url":   "https://example.com",
  "source": "newsletter-acg-47" }
```

Returns:

```json
{ "ok": true, "id": 12345, "message": "Audit request received. Report coming to your inbox in ~5 minutes." }
```

## Fulfillment loop

1. Notification email arrives in your inbox.
2. Open **WP Admin → Audit Requests**, click the new entry.
3. Run `/cc-seo-audit-report` (or the appropriate audit) against the URL.
4. Edit the CPT post and update the `_cc_audit_status` meta to `delivered`
   (or just mark "done" in your own workflow — the CPT is a log, not a state
   machine for v1).

## What's deliberately NOT in v1

- **HMAC link signing.** Anyone with a button URL could swap the email/domain.
  Blast radius is "Brent gets a spam audit request" — manageable. Add HMAC in
  v2 when (a) volume justifies it or (b) we automate fulfillment.
- **Automated audit pipeline.** Click → log → notify. The audit itself runs
  manually via Claude Code.
- **HubSpot timeline event** on the contact record. Easy to add later via
  HubSpot Private App + `/crm/v3/timeline/events`.
- **Rate limiting.** Trust the click for now.

## Files

- `includes/class-requestdesk-audit-capture.php` — CPT, REST, shortcode, notify.
- `requestdesk-connector.php` — autoloads + boots the class.
