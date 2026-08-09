# Tickets public API

This document describes the verified public interface of Tickets 1.0.31
(`version` 131). It is stronger than README for method usage, but the installed
module version and live site configuration remain authoritative for a specific
ProcessWire site.

All examples assume the ProcessWire namespace and a feature-detected module:

```php
<?php namespace ProcessWire;

if(!$modules->isInstalled('Tickets')) return;
/** @var Tickets $tickets */
$tickets = $modules->get('Tickets');
```

## Return and error conventions

- Record methods return associative arrays. A missing or inaccessible record is
  usually `[]`.
- List methods return arrays; paginated results return a metadata array.
- Invalid input may throw `WireException` or `Wire404Exception`.
- Authorization failure may throw `WirePermissionException` or return `[]`, as
  noted below.
- Browser controllers must catch errors, log protected details and render a safe
  public message.
- Every browser-originated write still requires ProcessWire CSRF validation in
  the consuming controller.

## Capability and permission checks

### `canManage(?User $user = null): bool`

True for a superuser or a user with `tickets-manage`.

### `canAdmin(?User $user = null): bool`

True for a superuser or a user with `tickets-admin`.

These methods do not imply that the caller has validated CSRF or ownership.

### `api(?User $actor = null): TicketsAgentApi`

Returns the operational facade for trusted integrations. It fails closed unless
the PHP API is enabled and the actor is logged in with both `tickets-api` and
`tickets-manage`. Its methods expose redacted ticket and attachment payloads,
not the low-level database-shaped records returned by `getTicket()`.

The facade provides `capabilities()`, `dashboard()`, `queue()`, `ticket()`,
`messages()`, `report()`, `forms()`, `update()`, `reply()`, and `note()`.
Report and form methods require `tickets-admin`; writes derive staff identity
from the actor and never accept a browser-provided staff flag.

### `capabilities(): array`

Returns a dependency-free manifest describing the PHP API, REST, and CLI
channels and the stable `tickets.*` capability names. A channel marked enabled
still requires its documented user permission, session, CSRF, or local-host
boundary.

## Versioned REST and CLI transports

REST is disabled by default and currently exposes version `v1` below
`/tickets-api/v1/`. The supported GET resources are `session`, `capabilities`,
`dashboard`, `queue`, `ticket`, `messages`, `report`, and `forms`. The supported
POST resources are `update`, `reply`, and `note`. Unsupported API versions fail
with HTTP 404, and every JSON envelope includes `api_version`.

Browser integrations may use the current ProcessWire session. Read the CSRF
credential from `GET /tickets-api/v1/session/` and send it with every POST.
Trusted non-browser clients may instead use the single optional credential
managed under **Setup → Tickets → Interfaces → API**:

```text
Authorization: Bearer tickets_v1_<one-time-secret>
Content-Type: application/json
```

The Bearer credential inherits its assigned ProcessWire actor's permissions,
is accepted only from the Authorization header, and is independently
rate-limited. Rotation invalidates the previous value immediately. Tickets does
not emit CORS headers and never accepts the token in URLs or request bodies.
The `session` resource remains session-only and does not exchange Bearer tokens.

The CLI is disabled by default and runs locally as
`php site/modules/Tickets/bin/tickets <command>`. Use `help` for the current
catalogue. Reads include `capabilities`, `dashboard`, `queue`, `ticket`,
`messages`, `report`, and `forms`; writes include `update`, `reply`, and `note`.
Maintenance commands include `automation`, `retention`, and `mailbox-import`.
Mutations require `--execute`; text writes accept bounded JSON through stdin
instead of command-line credentials.

## Labels and frontend presentation

```php
types(): array
categories(): array
topics(): array
priorities(): array
statuses(): array
formFieldTypes(): array
frontendFrameworks(): array
frontendUi(): array
text(string $key): string
frontendAttributes(string $role, array $extra = []): string
supportSchedule(): array
consentLabelHtml(): string
```

`categories()` is a backwards-compatible alias of `types()`. The option methods
return stable key-to-label maps from saved configuration. Do not store labels as
identifiers. `frontendAttributes()` returns escaped attributes for a documented
presentation role and optional extra attributes.

`supportSchedule()` is informational; support hours do not block submission.

## Guest submission proof

### `guestFormProof(): array`

Returns:

```php
[
    'issued_at' => 1720000000,
    'signature' => 'hmac-hex-value',
]
```

Include both values in a guest form as `form_issued_at` and `form_issued_sig`.
The proof participates in anti-spam timing validation; it is not a replacement
for ProcessWire CSRF.

## Creating tickets

### `createTicket(User $user, array $data, ?array $upload = null): array`

Creates a ticket and initial message, applies routing/SLA, optionally stores one
validated attachment and attempts configured notifications.

Supported input includes:

```php
[
    'customer_name' => 'Ada Example',
    'customer_email' => 'ada@example.com', // required for guests
    'subject' => 'Cannot access my account',
    'body' => 'Description of the problem',
    'category' => 'account',
    'topic' => 'account',
    'priority' => 'normal',
    'privacy_consent' => 1,
    'website' => '',                       // guest honeypot
    'form_issued_at' => 1720000000,
    'form_issued_sig' => 'hmac-hex-value',
    'form_id' => 0,
    'custom_data' => [],
    'context_type' => 'page',
    'context_id' => 123,
    'context_url' => 'https://example.com/contact/',
]
```

The exact required fields depend on whether `$user` is logged in and on saved
consent/spam settings. Values are sanitized and bounded by the module. `$upload`
uses the PHP `$_FILES` single-file shape.

For a guest, the module grants the current session access and sends the private
token through the configured creation notification. The return value does not
expose the plaintext token. Never log guest hashes or access links.

## Customer ticket access

### `ticketsForUser(User $user): array`

Returns the logged-in user's tickets ordered by update time. Returns `[]` for a
guest. It is not a staff queue API.

### `ticketByKey(string $key, User $user): array`

Returns a decorated ticket only when `canViewTicket()` succeeds. Use this for a
member or already-unlocked guest conversation.

### `unlockGuestTicket(string $key, string $token): array`

Validates the 64-character private token hash, grants a session-scoped ticket
access flag and returns the decorated ticket. Returns `[]` on failure. The
access route must be private/no-store and should redirect to a token-free URL
after success.

### `unlockGuestTicketByEmail(string $key, string $email): array`

Validates the normalized customer email for a guest-owned ticket, applies a
bounded session attempt limit, grants session access, and returns the decorated
ticket. Returns `[]` for a mismatch. Public templates must use one generic
failure message so they do not disclose whether the ticket key or email exists.

### `issueGuestBrowserAccessToken(string $key, User $user, int $ttlDays = 30): string`

After a guest ticket has been unlocked in the current session, returns a
ticket-scoped, HMAC-signed browser grant valid for at most 30 days. It contains
no email or private email-link credential and becomes invalid when the ticket's
private access hash changes. Returns an empty string for logged-in users or an
unauthorized ticket.

### `unlockGuestTicketFromBrowser(string $key, string $token): array`

Validates a browser grant, expiry, ticket binding, signature, and current guest
access hash before restoring session access. Returns `[]` on any mismatch.
Store this scoped grant rather than the email-link token for remembered browser
access.

### `canViewTicket(array $ticket, User $user): bool`

True for support staff, the owning logged-in user or a session with guest access
to that ticket.

### `getTicket(int $id): array`

Low-level record lookup. **It performs no ownership or permission check.** Do
not use it as a frontend authorization API. It is appropriate only after an
outer staff check or inside trusted backend orchestration.

## Conversation writes

### `addReply(int $ticketId, User $user, string $body, ?array $upload = null, ?bool $staff = null): array`

Adds a public conversation reply, stores an optional attachment, updates status
and sends configured notification. Customers must be able to view the ticket.
Staff status normally derives from `canManage($user)`; do not pass a browser
value into `$staff`.

### `addInternalNote(int $ticketId, User $user, string $body): array`

Requires `tickets-manage`. Adds a staff-only note. Never include these notes in
customer output.

### `reopenTicket(int $ticketId, User $user): array`

Requires visibility of the ticket. Reopens resolved/closed tickets into
`waiting_staff`; other statuses are returned unchanged.

### `rateTicket(int $ticketId, User $user, int $rating, string $comment = ''): array`

Requires visibility and a resolved/closed ticket. Rating must be 1 through 5;
the optional comment is bounded.

## Conversation reads

### `ticketMessages(int $ticketId, bool $includeInternal = false): array`

Returns messages with attachment metadata in ascending order. **It does not
authorize the ticket.** Authorize first. Pass `true` only for a staff view after
`canManage()`.

### `ticketLinks(int $ticketId): array`

Returns related/duplicate/parent/child links. It does not enforce a user check;
use it only in an authorized staff context.

## Private attachments

### `attachment(int $id, string $token, User $user): array`

Looks up a file by ID and access token and verifies ticket visibility. Returns
`[]` if any check fails.

### `attachmentPath(array $attachment): string`

Returns the private filesystem path for an already-authorized attachment.
Never call it on request data or an unverified database-shaped array.

### `attachmentUrl(array $ticket, array $attachment): string`

Builds the route URL under current `public_path`. It does not by itself grant
access; the download controller must call `attachment()`.

## Reusable forms

### `formBuilderImporter(): TicketsFormBuilderImporter`

Returns the optional migration adapter. Use `available()` before presenting an
import action, `candidates($adminUser)` to preview mappings and
`import($formId, $adminUser)` to create or refresh a disabled `fb-*` form draft.
Both data methods require a user with `tickets-admin`; the public site never
depends on FormBuilder after import.

### `customForms(bool $enabledOnly = false): array`

Returns all or only enabled custom-form definitions. The all-forms view is
administrative configuration and requires an outer `canAdmin()` check.

### `customForm($identifier): array`

Returns a decorated definition by numeric ID or slug, or `[]`.

### `renderFormEmbed(string $name, array $defaults = []): string`

Returns a cache-safe frontend placeholder plus module assets for an enabled
form. Defaults are filtered to defined form fields and stored as data
attributes. This is the preferred output inside otherwise cacheable pages.

### `renderCustomForm(string $name): string`

Returns runtime form HTML containing CSRF and guest-proof values. The endpoint
must be private/no-store. Returns an empty string for a missing/disabled form
and a sign-in alert when guests are not allowed.

### `submitCustomForm(string $name, User $user, array $data, ?array $upload = null): array`

Validates the form definition and values, then creates a ticket. Returns:

```php
['ticket' => $ticket, 'form' => $form]
```

The site controller must validate ProcessWire CSRF before calling it.

### `saveCustomForm(array $data, User $user): array`

Requires `tickets-admin`. Creates or updates a definition.

### `deleteCustomForm(int $id, User $user): void`

Requires `tickets-admin`. Refuses to delete a form that already has
submissions; disable it instead.

## Staff operations

### `updateTicket(int $ticketId, User $user, array $data): array`

Requires `tickets-manage`. Supports validated `status`, `priority` and
`assigned_user_id` updates and records the event.

### `linkTicket(int $ticketId, int $relatedTicketId, string $type, User $user): void`

Requires `tickets-manage`. Types are `related`, `duplicate`, `parent` or
`child`; invalid values fall back to `related`.

### `mergeTicket(int $ticketId, int $targetId, User $user): array`

Requires `tickets-manage`. Marks the source ticket closed and links it as a
duplicate of the target. This is a material mutation and should be confirmed in
the UI.

### `bulkUpdateTickets(array $ids, string $operation, string $value, User $user): int`

Requires `tickets-manage`. Accepts 1–100 unique positive IDs. Operations:

- `status` with a configured status key;
- `priority` with a configured priority key;
- `assign` with `0` or a valid support-agent user ID.

Returns the number changed.

## Queue and reports

### `queue(array $filters = []): array`

Convenience wrapper returning only `queuePage()['items']`. Requires an outer
`canManage()` check.

### `queuePage(array $filters = [], int $page = 1, int $limit = 50, string $scope = 'active'): array`

Requires an outer `canManage()` check. Filters support `status`, `category`,
`topic`, `priority`, `assigned_user_id`, `date_from`, `date_to` and `q`.
`scope` is `active`, `closed` or another value for all. Limit is clamped to
10–100. Returns:

```php
[
    'items' => [],
    'total' => 0,
    'page' => 1,
    'pages' => 1,
    'limit' => 50,
]
```

### `dashboardStats(): array`

Aggregated operational metrics, status/type/topic breakdowns and a 14-day
trend. Requires an outer `canManage()` check.

### `reportData(array $filters = []): array`

Operational report for 7–365 days (`filters['days']`, default 30), including
summary, agents, types, backlog, daily data and the last maintenance run.
Requires an outer `canManage()` check.

### `slaState(array $ticket): array`

Returns:

```php
[
    'phase' => 'first_response',
    'due_at' => '2026-07-31 12:00:00',
    'breached' => false,
    'remaining_seconds' => 3600,
]
```

## Routing, macros and mail templates

```php
routingRules(bool $enabledOnly = false): array
saveRoutingRule(array $data, User $user): array
macros(bool $enabledOnly = true, array $ticket = []): array
saveMacro(array $data, User $user): array
mailTemplateDefaults(): array
mailProviderOptions(): array
mailProviderLabel(): string
mailTemplates(): array
saveMailTemplate(string $key, string $subject, string $htmlBody, User $user): void
```

All save methods require `tickets-admin`. The read methods do not independently
authorize the caller; treat rules, macros and templates as staff/admin data.
Provider options contain installed `WireMail*` modules plus the site default.
Tickets stores only the selected module class, never its credentials.

## AI reply drafting

### `suggestReply(array $ticket, array $messages, User $user): array`

Requires `tickets-manage`, enabled assistance and installed Squad. It may use
Atlas and Knowledge Base when independently installed and enabled. It returns a
draft and supporting metadata or throws when required assistance is unavailable.

This call sends supplied ticket content to the configured provider through
Squad. Require staff intent and review. Never send the returned text, change a
ticket or promise an action automatically.

## Automation and retention

### `runAutomation(bool $dryRun = false): array`

Finds bounded SLA breaches and resolved tickets due for auto-close. A real run
updates tickets and may send escalation mail. No user permission is checked;
call from trusted CLI/cron or after explicit admin authorization.

### `runRetention(bool $dryRun = false): array`

Processes a configured bounded batch of old closed tickets. Action is
`anonymize` or `delete`. Retention is disabled when `retention_days` is zero.
No user permission is checked. Always run dry first; a real run requires backup
and explicit approval.

Canonical CLI:

```bash
php site/modules/Tickets/bin/tickets automation --dry-run --root=/path/to/site
php site/modules/Tickets/bin/tickets automation --execute --root=/path/to/site
php site/modules/Tickets/bin/tickets retention --dry-run --root=/path/to/site
php site/modules/Tickets/bin/tickets retention --execute --root=/path/to/site
```

## Resend inbound replies

### `inboundReplyAddress(array $ticket): string`

Builds `local+publickey@domain` from the configured receiving address. Returns
an empty string when configuration or the key is missing.

### `handleResendWebhook(string $rawBody, array $headers): array`

Requires inbound replies to be enabled. It verifies the Svix signature,
deduplicates the event, retrieves `email.received` content through installed
`Resend`, matches the ticket key and verifies the sender against the ticket
customer before appending a reply.

Pass the exact raw request body and original headers. The endpoint must bypass
page cache, return no-store, avoid rendering a site shell and never log the
secret or full email body.

## Optional Mailbox integration

### `mailboxIntegrationStatus(): array`

Returns non-secret installation, compatibility, credential-readiness, background-sync, SMTP, and redacted enabled-account status. It never returns a mailbox username, password, OAuth token, host, subject, sender, or body.

### `importMailboxNotification(array $notification, string $actor = 'backend'): array`

Trusted hook/worker entry point for the identifier payload emitted by `Mailbox::messageIndexed()`. It requires the Tickets integration setting, the selected account/folder, configured Mailbox credentials, and Mailbox background synchronization. Account or folder mismatches return an ignored result without reading the message.

### `importMailboxMessage(int $accountId, string $folder, int $uid, string $actor = 'backend'): array`

Trusted bounded import for one selected message. Tickets calls Mailbox `getAgentMessage()` inside `withAccount()`, so HTML, raw MIME, executable URL targets, credentials, and attachment bytes are excluded. By default the configured `support_email` must appear in To or Cc. A new message creates a ticket; `[Ticket KEY]`, `Ticket #KEY`, or `ticket+KEY@…` appends a reply only when the sender matches the existing ticket customer. Results are `ticket_created`, `reply_added`, `ignored`, or `duplicate` with identifiers/reason only.

Sources are deduplicated by account/folder/UID and account/Message-ID in `tickets_mailbox_messages`. Initial Mailbox synchronization intentionally emits no events, so enabling the bridge never imports historical mail or downloads an entire account. The bridge stores no attachments and performs no automatic AI classification.

### `importMailboxInbox(int $limit = 25, bool $execute = false): array`

Trusted CLI/maintenance helper for a bounded page of the newest messages in the configured account/folder. The default preview reads summaries only and returns counts without importing; `execute=true` fetches and recognizes at most 100 individual messages through the same idempotent safe path. Canonical CLI: `php site/modules/Tickets/bin/tickets mailbox-import --limit=25 --root=/path/to/processwire` for preview, then repeat with `--execute` after review.

When both `mail_enabled` and `mailbox_outbound_enabled` are true and Mailbox SMTP is ready, existing Tickets notifications use Mailbox plain-text delivery. Customer notifications with a linked inbound source use `replyMessage()` to preserve threading; other notifications use `sendMessage()`. When this option is off, the existing selected WireMail provider remains unchanged.

## Configuration keys

The stable 1.x configuration keys are:

```text
public_path
support_email
from_email
from_name
mail_module
mail_enabled
max_image_mb
allowed_attachment_types
support_days
support_start
support_end
support_timezone
guest_ticket_limit_hour
spam_min_submit_seconds
spam_blocked_domains
spam_blocked_terms
consent_required
consent_label
privacy_policy_url
terms_url
ticket_types
ticket_topics
ticket_priorities
frontend_framework
frontend_custom_map
sla_first_response_minutes
sla_resolution_minutes
auto_close_days
sla_escalation_email
retention_days
retention_action
retention_batch_size
enable_agent_api
enable_rest_api
enable_cli
rest_bearer_token_hash
rest_bearer_user_id
rest_bearer_token_created_at
telegram_notifications_enabled
telegram_bot_token
telegram_chat_ids
telegram_notification_events
telegram_timeout_seconds
resend_inbound_enabled
resend_inbound_address
resend_webhook_secret
mailbox_inbound_enabled
mailbox_outbound_enabled
mailbox_account_id
mailbox_folder
mailbox_require_support_recipient
ai_assist_enabled
ai_provider_model
ai_system_prompt
ai_atlas_enabled
atlas_collection
ai_knowledge_base_enabled
ai_knowledge_base_limit
```

Use ProcessWire's module configuration UI/API rather than setting properties in
a request. Configuration changes can affect privacy, delivery, routes and data
lifecycle and therefore require the approval rules in `AGENTS.md`.

The three `rest_bearer_*` values are internal credential metadata managed from
**Setup → Tickets → Interfaces → API**. Do not write or display them in a site
template. Tickets stores only a SHA-256 hash, actor ID, and rotation timestamp;
the raw token is shown once and belongs in a secret manager.

The `telegram_bot_token` and `telegram_chat_ids` values are outbound credentials
managed under **Modules → Configure → Tickets → Operational interfaces**. Never
return them from a site template, API response, capability manifest, or log.
Private runtime overrides are preferred for production deployments.

### `telegramIntegrationStatus(): array`

Returns TeleWire installation/compatibility, configuration, enabled, ready,
recipient count, credential source, and selected-event state. It does not test
the Telegram network connection and never returns bot tokens or chat IDs.

### `telegramNotificationEvents(): array`

Returns the normalized enabled subset of `new_ticket`, `customer_reply`, and
`sla_breach`. Telegram delivery stays disabled unless
`telegram_notifications_enabled` is explicitly enabled and Tickets has a valid
bot token plus at least one valid recipient configured.

## Hooks and internal APIs

`Tickets::mailboxMessageImported(array $result)` is a hookable identifier-only result boundary for an optional Mailbox import. Its payload contains outcome, reason when ignored, ticket/message identifiers when created, account ID, and UID; it contains no email text or addresses. Do not perform slow delivery inside this hook.

No other stable custom domain-hook contract is declared. Do not hook private methods or `ProcessTickets` render/execute internals. ProcessWire module lifecycle methods, `TicketsMailboxBridge`, and `TextformatterTicketsForms::format()` are framework integration points, not general site-facing service APIs.

Do not query `tickets_*` tables from site templates. Do not depend on table
names, private storage layout, admin query parameters or undocumented decorated
record fields. Request a documented API addition when an integration needs a
missing capability.

## Compatibility

- ProcessWire 3.0.200 or newer.
- PHP 8.1 or newer.
- Any correctly configured ProcessWire `WireMail*` provider for outbound mail.
- Optional: Resend, Squad, Atlas, Knowledge Base and TinyMCE.
- Full-page caches are compatible only when private support routes bypass shared
  HTML caching.
