# Tickets

Tickets adds private customer-support conversations to ProcessWire: account and
guest requests, staff workflows, attachments, automation, reports and
transactional notifications.

![Tickets](assets/readme-doodle.png)

It is made for product sites, member portals and service organizations that
need support operations inside ProcessWire without exposing conversations or
private files publicly.

- **Author:** Maxim Semenov
- **Website:** [smnv.org](https://smnv.org)
- **Email:** [maxim@smnv.org](mailto:maxim@smnv.org)
- **Release:** 1.0.45 (`version` 145)

If this project helps your work, consider supporting future development:
[GitHub Sponsors](https://github.com/sponsors/mxmsmnv) or
[smnv.org/sponsor](https://smnv.org/sponsor/).

## What Tickets Does

- Creates private support tickets for authenticated accounts and guests.
- Gives guests hashed private-access links without requiring registration.
- Supports remembered guest access after one email check or private-link visit
  using a scoped signed browser grant rather than the email address as a token.
- Keeps replies, sent/delivered/read timestamps, staff-only notes, attachments
  and workflow history together.
- Provides statuses, priorities, assignment, bulk actions and ticket merging.
- Includes SLA targets, routing rules, reusable replies and scheduled automation.
- Includes operational charts, reports, CSV export and customer satisfaction ratings.
- Provides reusable custom forms with validation, live preview and rich-text embeds.
- Can copy existing Pro FormBuilder definitions into independent, reviewable Tickets form drafts when FormBuilder is installed.
- Supports configurable consent, spam protection, retention and attachment rules.
- Sends editable transactional messages with recipient-correct links and
  customizable shared headers and footers through a selectable ProcessWire
  WireMail provider.
- Supports optional authenticated Resend inbound replies.
- Supports optional Mailbox ingestion for new support email and Mailbox SMTP delivery for ticket notifications and linked thread replies.
- Supports opt-in administrator Telegram alerts for new tickets, customer
  replies, and SLA breaches through TeleWire while keeping all credentials and
  recipient settings independently in Tickets.
- Supports optional Squad reply drafts grounded by Atlas and Knowledge Base;
  drafts use the complete public conversation and can be copy-edited separately.
- Records a non-IP location and timezone snapshot when GeoIP is installed, so
  staff can see customer-local time without storing a new raw IP field.
- Provides independently disabled, permission-gated PHP API, versioned JSON
  REST, and local JSON CLI channels for trusted integrations and agents.
- Shows Email alongside API, CLI, and Telegram in the Interfaces workspace,
  including the selected WireMail provider without exposing its credentials.
- Lets administrators choose oldest-first (ASC) or newest-first (DESC)
  conversation presentation; DESC also places the reply composer first.
- Supports session/CSRF REST authentication and an optional scoped Bearer
  credential stored only as a hash and assigned to one permitted actor.
- Uses neutral installation defaults and creates no sample tickets or customers.

## Admin Area

Tickets adds a dedicated **Setup → Tickets** workspace where support staff can:

- monitor queue health and response metrics;
- search and filter active, resolved and closed tickets;
- work tickets in priority, active-state and SLA-risk order;
- assign, prioritize and update requests individually or in bulk;
- reply, improve writing, inspect message receipts, edit subjects, extend SLA,
  add internal notes, link duplicates and inspect private attachments;
- manage routing rules, SLA overrides and reusable replies;
- build reusable intake forms and preview them before publishing;
- edit transactional email templates;
- review reports, customer ratings and retention runs.
- inspect API, CLI, and Telegram availability in separate workspaces, including
  versioned routes, Bearer credential state, Telegram recipients and events,
  security requirements, and ready-to-run local commands.

Staff need the `tickets-manage` permission. Configuration and destructive
administration can be restricted with `tickets-admin`.

## Public Portal and Forms

The module provides the ticket domain, validation, permissions, storage and
rendering helpers. It registers a ProcessWire `tickets` template and a public
support page, but it does not overwrite the site's theme or install files into
`site/templates/`.

The consuming site supplies `site/templates/tickets.php` and composes account
lists, ticket conversations, guest access, private downloads and optional
inbound webhooks using the documented public API.

Editors can place reusable forms in formatted fields with either token:

```text
[[tickets-form:report-incorrect-data]]
[tickets-form name="report-incorrect-data"]
```

Developers can render the same cache-safe placeholder from PHP:

```php
echo $modules->get('Tickets')->renderFormEmbed('report-incorrect-data');
```

Existing FormBuilder forms can be copied from **Tickets → Forms → Import from
FormBuilder**. The migration is one-way: FormBuilder remains unchanged and the
result no longer requires FormBuilder at runtime. Imported forms are disabled
until an administrator reviews routing, consent copy, field mappings and the
shared protected attachment control.

The Forms workspace keeps disabled-form previews inside the authenticated form
editor. Only enabled forms open on the configured public portal.

## Optional Integrations

- Any ProcessWire `WireMail*` provider for outbound transactional email.
- Resend for authenticated inbound email replies.
- Mailbox for bounded, deduplicated support-email ingestion and optional SMTP delivery; existing WireMail and Resend paths remain independent.
- TeleWire 1.0.2+ as the Telegram transport for administrator alerts. Tickets
  owns separate credentials, recipients, events, and runtime overrides.
- TinyMCE for visual transactional-template editing.
- Squad for staff-requested reply drafts.
- Atlas and Knowledge Base for independently optional grounding sources.
- Full-page caches when the complete support route and private endpoints bypass shared HTML caching.

Core ticket creation, staff workflows and reporting continue to work when these
optional modules are not installed.

## Admin Interface Language

English is the source language. Bundled ProcessWire translation files are
included for Dutch, French, German, Italian and Spanish in `languages/`.
Install them through ProcessWire Language Support; missing strings fall back to
English.

## Requirements

- ProcessWire 3.0.200 or newer.
- PHP 8.1 or newer.
- A configured WireMail provider only when transactional notifications are enabled.

## Installation

1. Copy the `Tickets` folder into `/site/modules/`.
2. Refresh modules in ProcessWire Admin.
3. Install **Tickets**.
4. Review the recipient, sender, public path, legal links and optional integrations.
5. Add the consuming `site/templates/tickets.php` portal template.
6. Explicitly enable transactional notifications after the selected provider is configured.
7. Grant `tickets-manage` and, where appropriate, `tickets-admin` to support roles.
8. If API access is required, grant `tickets-api` separately and enable only
   the required PHP, REST, or CLI channel. All three are disabled by default.

The installer creates its tables, permissions, formatter, private attachment
storage, ProcessWire template and public page. It does not create sample
customers, tickets, conversations, forms, routing rules or macros. Upgrades
retain saved configuration, edited mail templates and ticket data.

## Documentation

See [DOCUMENTATION.md](DOCUMENTATION.md) for configuration, frontend routing,
custom forms, email, automation, retention and caching.

See [API.md](API.md) for exact public calls, permission boundaries and return
contracts. AI agents and automation maintainers should also read
[AGENTS.md](AGENTS.md) before recommending or integrating the module.

See [CHANGELOG.md](CHANGELOG.md) for the release notes.

## Sponsorship

Development and maintenance are supported through
[GitHub Sponsors](https://github.com/sponsors/mxmsmnv) and
[smnv.org/sponsor](https://smnv.org/sponsor/).

Sponsorship helps fund compatibility testing, security maintenance,
documentation and continued ProcessWire support. It does not change the MIT
license or create preferential access to customer ticket data.

## Author

Maxim Semenov

[smnv.org](https://smnv.org)

[maxim@smnv.org](mailto:maxim@smnv.org)

## License

MIT. See [LICENSE](LICENSE).
