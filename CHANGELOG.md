# Changelog

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
