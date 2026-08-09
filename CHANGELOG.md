# Changelog

## 1.0.30 - 2026-08-09

- Restored TeleWire as the required Telegram transport while keeping bot token,
  recipients, events, timeout, and enable state independently owned by Tickets.
- Switched delivery to TeleWire 1.0.2's credential-scoped `createClient()` API;
  Tickets never reads or changes TeleWire module settings.
- Added TeleWire compatibility state to the Telegram interface and module
  settings without exposing either module's credentials.

## 1.0.29 - 2026-08-09

- Kept Telegram credentials, recipients, events, timeout, and privacy guidance
  visible while delivery is disabled so administrators can configure first and
  enable only after the interface reports ready.

## 1.0.28 - 2026-08-09

- Replaced the optional TeleWire dependency with an independent Tickets-owned
  Telegram Bot API transport, credentials, recipients, events, and timeout.
- Added Telegram as a first-class Interfaces pill and overview card with a
  dedicated readiness and privacy screen built from existing module components.
- Added private runtime credential and recipient overrides, strict local input
  validation, HTTPS-only delivery, redirect rejection, bounded timeouts, and
  credential-free status and logging.

## 1.0.27 - 2026-08-09

- Prefer TeleWire 1.0.1's public, network-free `isConfigured()` readiness API while retaining a fail-closed compatibility fallback for TeleWire 1.0.0.

## 1.0.26 - 2026-08-09

- Added opt-in administrator Telegram notifications through mxmsmnv/TeleWire
  for new tickets, customer replies, and first-time SLA breaches.
- Added fail-closed integration status and event settings while keeping bot
  credentials and chat recipients exclusively in TeleWire.
- Limited Telegram payloads to operational ticket metadata and the authenticated
  admin link; customer email, message text, guest tokens, custom data, and
  attachments remain excluded.

## 1.0.25 - 2026-08-09

- Reused the module's established admin-navigation pill component for the API
  and CLI subsection instead of maintaining a separate visual variation.

## 1.0.24 - 2026-08-09

- Rendered every Overview, API, and CLI interface tab as a complete outlined
  pill with filled active, hover, and keyboard-focus states.

## 1.0.23 - 2026-08-09

- Split the operational interface workspace into dedicated API and CLI pages
  with channel status, versioned REST route documentation, and a complete CLI
  command catalogue.
- Added opt-in Bearer authentication for REST with one-time credential display,
  SHA-256 hash-at-rest storage, actor-scoped permissions, independent rate
  limiting, immediate rotation/revocation, and header-only token acceptance.
- Added explicit REST API version dispatch and version metadata to every JSON
  response while preserving session authentication and CSRF for browser writes.

## 1.0.22 - 2026-08-09

- Stacked the report-chart heading and legend on mobile so both series remain
  fully readable without squeezing or clipping.

## 1.0.21 - 2026-08-09

- Replaced the subtle volume-chart key with a prominent accessible legend that
  shows each series color, label, and total for the selected reporting period.

## 1.0.20 - 2026-08-09

- Isolated the dashboard and All tickets table layouts so their different
  column structures no longer reuse incompatible fixed widths.
- Restored readable Priority and SLA columns on desktop while keeping the
  dashboard's compact card layout limited to mobile and tablet views.

## 1.0.19 - 2026-08-08

- Made the icon-only ticket-subject confirmation control a true circle and
  centered it beside the editable subject at every heading size.

## 1.0.18 - 2026-08-08

- Aligned message receipt status text and icons with the message timestamp.
- Closed an open receipt-details popover when staff click elsewhere or press
  Escape, while preserving interaction inside the popover.

## 1.0.17 - 2026-08-08

- Made every reply notification use its immutable message ID as the
  idempotency key, so two messages created within the same second cannot cause
  a customer-to-staff notification to be suppressed as a duplicate.

## 1.0.16 - 2026-08-08

- Added a consistent dashboard gap between the active-ticket queue and the
  following metric grid.

## 1.0.15 - 2026-08-08

- Kept long editable subjects within the case header using a compact accessible
  save control, and hid SLA extension controls when no active deadline exists.

## 1.0.14 - 2026-08-08

- Added a validated canonical notification origin so links generated during
  cron and CLI runs use the same HTTPS site as web requests.

## 1.0.13 - 2026-08-08

- Reworked the ticket workspace with editable subjects, an SLA extension
  control, compact conversation receipts with exact sent/delivered/read dates,
  and customer-local time when GeoIP supplies a valid timezone.
- Added non-IP customer location snapshots from the optional GeoIP module and
  read-receipt APIs for staff and customer portal views.
- Made AI drafts consider every public message in chronological order within a
  bounded prompt, and added a separate grammar-and-clarity editing action that
  preserves facts and commitments.
- Corrected recipient-specific notification links: staff messages now open the
  admin ticket while customers receive the public or private portal URL.
- Added configurable, sanitized shared HTML email headers and footers and made
  the template preview render the complete notification wrapper.
- Expanded reports with daily created/completed charts plus status and priority
  distributions.

## 1.0.12 - 2026-08-08

- Reordered the dashboard queue to Priority, Status, Ticket, and Activity,
  folding customer and SLA context into the two relevant cells.
- Reduced priority to an accessible red, yellow, green, or neutral dot on
  mobile and tablet while retaining its text label on desktop.
- Made the complete queue row keyboard- and pointer-activatable below the
  desktop breakpoint, while keeping the explicit open button on desktop.

## 1.0.11 - 2026-08-08

- Moved the active-ticket work queue directly below the dashboard introduction
  so support staff reach actionable requests before metrics and reports,
  especially on narrow mobile screens.
- Kept dashboard metrics visible when the active queue is empty instead of
  returning early from the page renderer.

## 1.0.10 - 2026-08-08

- Registered the opt-in REST route from Tickets' conditional autoload instead
  of a newly discovered child module, making existing-site upgrades complete
  in one ProcessWire module refresh while keeping disabled sites unloaded.

## 1.0.9 - 2026-08-08

- Fixed local CLI authorization by explicitly making the discovered superuser
  the current ProcessWire CLI actor before entering the gated facade.

## 1.0.8 - 2026-08-08

- Fixed API and CLI workspace labels and command examples so ampersands and
  line breaks render correctly in the ProcessWire admin.

## 1.0.7 - 2026-08-08

- Added independently disabled PHP agent API, same-origin JSON REST, and local
  CLI channels with a stable capability manifest.
- Added the `tickets-api` permission, session authentication, CSRF on every
  REST mutation, bounded request bodies and rate limits, no-store security
  headers, and strict method/resource validation.
- Added redacted operational payloads that never expose guest access hashes,
  attachment access tokens, or private storage names.
- Expanded the JSON CLI with capabilities, dashboard, queue, ticket,
  conversation, report, form, update, reply, and note commands. Every mutation
  and non-preview maintenance run now requires `--execute`.
- Added an API & CLI workspace explaining live channel status, endpoints,
  commands, permissions, and the security boundary.
- Added integration coverage for capability gating, guest denial, payload
  redaction, bounded queue reads, and ticket updates.

## 1.0.6 - 2026-08-08

- Ordered staff queues deterministically by priority, active state, breached or
  nearest SLA deadline, workflow status, and recent activity.
- Added an SLA column to the dashboard and unified ticket workspace so staff can
  see why a request appears earlier in the queue.

## 1.0.5 - 2026-08-08

- Kept disabled-form previews inside the authenticated form editor so drafts no
  longer open a public 404 page.
- Kept active-form previews on the configured public portal and clarified the
  distinction between public and draft preview actions.

## 1.0.4 - 2026-08-02

- Added a config-conditional Tickets autoload fallback so an existing installation registers the Mailbox hook after one normal upgrade even when ProcessWire discovers the new bridge module only at the end of that refresh.

## 1.0.3 - 2026-08-02

- Added an optional `TicketsMailboxBridge` that consumes new-message events only when both Tickets inbound integration and Mailbox background synchronization are enabled.
- Added bounded agent-safe email recognition: support-address filtering, deterministic ticket-key matching, sender verification for replies, and idempotent account/folder/UID plus Message-ID source tracking.
- Added a preview-first `mailbox-import` CLI command for an explicitly bounded newest-message catch-up; mutation requires `--execute` and never expands beyond 100 messages.
- Added optional Mailbox SMTP delivery for Tickets notifications, including continuation of a linked inbound email thread when possible.
- Kept Mailbox, SMTP delivery, WireMail, Resend, and all automatic ingestion disabled or independent by default; initial Mailbox seeding never imports historical email.

## 1.0.2

- Preserved FormBuilder fields whose names overlap Tickets security controls by assigning safe imported names.

## 1.0.1

- Added an optional, one-way FormBuilder importer for reusable Tickets forms.
- Added portable section and date fields and raised the safe field limit for long intake forms.
- Generalized custom-form attachment copy for supported protected file types.

## 1.0.0 - 2026-07-31

Initial public release.

### Included

- Account and guest support tickets with private access links, threaded replies,
  attachments, ratings, reopening and an immutable event trail.
- Staff dashboard, unified queue, filters, assignment, bulk actions, internal
  notes, related tickets, merging, reports and customer-satisfaction metrics.
- Configurable types, topics, priorities, support hours, legal consent, spam
  controls, retention, SLA targets, routing rules, quick replies and automation.
- Reusable custom forms with field constraints, live admin preview, shortcode
  embeds and framework-neutral frontend attributes, plus optional one-way
  FormBuilder migration into independent Tickets form drafts.
- Selectable ProcessWire WireMail delivery, editable transactional templates and
  optional authenticated Resend inbound replies.
- Optional bounded Mailbox ingestion and Mailbox SMTP delivery with strict
  sender checks, deduplication and preview-first catch-up tooling.
- Optional Squad reply drafts grounded independently by Atlas and Knowledge Base.
- ProcessWire permissions, private attachment storage, CLI automation and
  retention workers, bundled translations and integration tests.
- Neutral installation defaults with no sample tickets or project-specific data;
  transactional mail remains disabled until explicitly configured.
- MIT licensing, release documentation and GitHub sponsorship metadata.

### Requirements

- ProcessWire 3.0.200 or newer.
- PHP 8.1 or newer.
