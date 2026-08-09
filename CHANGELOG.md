# Changelog

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

## 1.0.0 - 2026-08-03

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
