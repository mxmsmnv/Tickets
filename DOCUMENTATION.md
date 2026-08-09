# Tickets Documentation

This document describes the integration contract implemented by Tickets 1.0.31
for ProcessWire.

## Configuration

Open **Modules → Configure → Tickets** to configure:

- public support path and frontend framework adapter;
- support recipient, sender identity and transactional mail provider;
- working days, hours and support timezone;
- ticket types, topics and priorities;
- attachment allowlist and maximum size;
- guest throttling, honeypot timing and blocked domains or terms;
- Privacy Policy and Terms of Use links;
- SLA targets, escalation and automatic closing;
- retention period, action and batch size;
- optional Resend inbound replies;
- optional Mailbox ingestion and SMTP delivery;
- independent optional administrator Telegram notifications;
- optional Squad, Atlas and Knowledge Base reply assistance.

Fresh installations use neutral placeholder addresses and keep transactional
delivery disabled. Review the values before enabling email.

## Frontend Integration

Tickets registers a ProcessWire template named `tickets` and creates the page
configured by `public_path`, `/support/` by default. It intentionally does not
write a file into `site/templates/` or assume a site shell.

Create `site/templates/tickets.php` in the consuming project. That template is
responsible for:

- rendering the support landing page and new-ticket form;
- validating ProcessWire CSRF before every write;
- dispatching ticket, guest-access, attachment, custom-form and inbound segments;
- escaping output and mapping exceptions to safe user-facing states;
- sending private/no-store headers;
- excluding the complete support route from shared full-page caches;
- sending `X-Robots-Tag: noindex, nofollow, noarchive` where appropriate.

Do not query Tickets tables from templates. Use the module API.

## Verified Public API

### Labels and presentation

- `types(): array`
- `categories(): array` — backwards-compatible alias of `types()`
- `topics(): array`
- `priorities(): array`
- `statuses(): array`
- `text(string $key): string`
- `supportSchedule(): array`
- `frontendFrameworks(): array`
- `frontendUi(): array`
- `frontendAttributes(string $role, array $extra = []): string`
- `consentLabelHtml(): string`

### Customer tickets

- `guestFormProof(): array`
- `createTicket(User $user, array $data, ?array $upload = null): array`
- `addReply(int $ticketId, User $user, string $body, ?array $upload = null, ?bool $staff = null): array`
- `reopenTicket(int $ticketId, User $user): array`
- `rateTicket(int $ticketId, User $user, int $rating, string $comment = ''): array`
- `ticketsForUser(User $user): array`
- `ticketByKey(string $key, User $user): array`
- `unlockGuestTicket(string $key, string $token): array`
- `canViewTicket(array $ticket, User $user): bool`
- `ticketMessages(int $ticketId, bool $includeInternal = false): array`

### Private attachments

- `attachment(int $id, string $token, User $user): array`
- `attachmentPath(array $attachment): string`
- `attachmentUrl(array $ticket, array $attachment): string`

Call `attachment()` before reading or streaming `attachmentPath()`. It verifies
the file token and ticket ownership or staff access. Images and PDFs may be
rendered inline; other validated files should download as attachments.

### Custom forms

- `formFieldTypes(): array`
- `customForms(bool $enabledOnly = false): array`
- `customForm($identifier): array`
- `renderFormEmbed(string $name, array $defaults = []): string`
- `formBuilderImporter(): TicketsFormBuilderImporter` for an optional, one-way
  migration of installed FormBuilder definitions into independent form drafts
- `renderCustomForm(string $name): string`
- `submitCustomForm(string $name, User $user, array $data, ?array $upload = null): array`

Administrative methods such as `saveCustomForm()` and `deleteCustomForm()`
enforce `tickets-admin` and are not frontend submission APIs.

### Staff and operations

- `canManage(?User $user = null): bool`
- `canAdmin(?User $user = null): bool`
- `addInternalNote(int $ticketId, User $user, string $body): array`
- `linkTicket(int $ticketId, int $relatedTicketId, string $type, User $user): void`
- `ticketLinks(int $ticketId): array`
- `mergeTicket(int $ticketId, int $targetId, User $user): array`
- `updateTicket(int $ticketId, User $user, array $data): array`
- `queuePage(array $filters = [], int $page = 1, int $limit = 50, string $scope = 'active'): array`
- `bulkUpdateTickets(array $ids, string $operation, string $value, User $user): int`
- `dashboardStats(): array`
- `reportData(array $filters = []): array`
- `slaState(array $ticket): array`
- `runAutomation(bool $dryRun = false): array`
- `runRetention(bool $dryRun = false): array`

## Operational interfaces

Tickets exposes API, CLI, Email, and Telegram as independent operational interfaces.
Fresh installations and upgrades keep every outbound or remotely accessible
channel disabled:

- the PHP facade from `$tickets->api($actor)`;
- versioned JSON REST under `/tickets-api/v1/`;
- the local `site/modules/Tickets/bin/tickets` executable.
- administrator alerts through the Telegram Bot API.
- transactional email status and the selected WireMail provider without
  exposing provider credentials.

The ticket workspace conversation order is configurable under **Modules →
Configure → Tickets → Staff workspace**. ASC keeps the oldest message first and
the reply composer below the thread. DESC places the composer first and lists
the newest entry first. The staff Conversation timeline includes messages,
internal notes and SLA-extension activity with its staff actor and new deadline.
SLA activity is not exposed as a customer message or sent as a transactional
notification. This affects admin presentation only; public APIs and AI context
remain chronological.

Saving an internal note is transactional: the private message, audit event and
ticket activity timestamp succeed or fail together. After saving, the staff
workspace returns directly to the new highlighted note in Conversation so the
result is visible without searching the thread.

The same Staff workspace section controls sidebar placement independently for
desktop (1200px and wider) and tablet (768–1199px). On phones below 768px the
workspace always uses one column with the conversation first, reply composer
second, and operational cards afterward, regardless of the desktop settings.

The PHP and REST channels require an actor with both `tickets-api` and
`tickets-manage`. Report and form-definition reads additionally require
`tickets-admin`. REST accepts either the existing ProcessWire session or one
explicitly generated Bearer credential assigned to an eligible ProcessWire
actor. Session POSTs require the CSRF token returned by
`GET /tickets-api/v1/session/`. Bearer POSTs authenticate through the
`Authorization: Bearer …` header and inherit the assigned actor's permissions.
Tickets stores only the SHA-256 token hash, shows the raw credential once, and
invalidates it immediately on rotation or revocation. Tokens are never accepted
from query strings or JSON bodies. The API deliberately emits no CORS headers.

Available REST resources are `session`, `capabilities`, `dashboard`, `queue`,
`ticket`, `messages`, `report`, `forms`, `update`, `reply`, and `note`. Responses
use either `{"ok":true,"api_version":"v1","result":...}` or
`{"ok":false,"api_version":"v1","error":"..."}`.
The transport is private/no-store and separately rate-limits reads and writes.
It never returns guest access hashes, attachment access tokens, or private
storage names.

The local CLI emits the same JSON envelope. Run `bin/tickets help` for the full
command reference. It never accepts credentials as arguments. Ticket writes,
real automation and retention runs, and mailbox imports require `--execute`;
automation and retention support an explicit `--dry-run` preview.

`queuePage()` uses the operational staff order: priority, active versus
completed state, breached or nearest SLA deadline, workflow status, then most
recent activity. The admin queue shows the same SLA state beside each ticket.

Methods that accept a `User` enforce their documented permission or ownership
boundary. Reporting, queue, automation and retention helpers do not all accept
a user argument; expose them only after an explicit `canManage()` or
`canAdmin()` check as appropriate. Callers must also validate CSRF for every
browser-originated write.

## Telegram administrator notifications

Open **Modules → Configure → Tickets → Operational interfaces** to configure a
Telegram bot token, administrator chat IDs, notification events, and delivery
timeout. Review the resulting state under **Setup → Tickets → Interfaces →
Telegram**. TeleWire 1.0.2+ is the required transport, but its saved bot token,
chat IDs, and notification settings are never used by Tickets. Telegram
delivery and every event remain opt-in and independent of transactional email.

The token may be stored in Tickets module configuration or injected privately
through `$config->ticketsTelegramBotToken` / `TICKETS_TELEGRAM_BOT_TOKEN`.
Recipient overrides use `$config->ticketsTelegramChatIds` /
`TICKETS_TELEGRAM_CHAT_IDS`. Runtime values take precedence over saved module
configuration. Status and capability output never includes credential values.
Tickets creates an integration-owned client through TeleWire's public
`createClient()` API. Requests use HTTPS only, reject redirects, and enforce
bounded connection and total timeouts. A delivery failure is logged without message content or
credentials and never rolls back ticket creation, a reply, or SLA automation.

The payload is deliberately minimal: event label, ticket key, subject,
priority, workflow status, and an authenticated admin URL. Customer email,
message body, guest access token, custom-form values, attachment names and
files are never included. Set `notification_origin` to the canonical HTTPS
origin so web, cron, and CLI notifications all generate the same admin URL.

## Custom Forms

Create reusable forms under **Setup → Tickets → Forms**. Supported fields are:

- single-line text;
- long text;
- email;
- URL;
- telephone;
- number;
- select;
- checkbox.

Forms support required state, width, independent minimum and maximum lengths,
placeholder, help text, select options, guest access, optional image attachment,
routing and a confirmation message.

Disabled forms are previewed only in the authenticated form editor. The public
preview action is available after a form is enabled, so draft definitions never
depend on a public route or reveal an incomplete intake flow.

Add `TextformatterTicketsForms` to a formatted field and use:

```text
[[tickets-form:report-incorrect-data]]
[tickets-form name="report-incorrect-data"]
```

Or render a cache-safe placeholder from PHP:

```php
echo $modules->get('Tickets')->renderFormEmbed('product-enquiry', [
    'product' => 'Example service',
]);
```

The placeholder loads the runtime form from
`{public_path}/form/{name}/`. The consuming support template must route GET to
`renderCustomForm()` and validated POST to `submitCustomForm()` and return
private/no-store responses.

## Transactional Email

Choose **Default (site WireMail setting)** or an installed `WireMail*` module in
Tickets settings. API keys and transport credentials remain in the provider
module; Tickets stores only the selected class name.

Set **Notification site origin** to the environment's canonical HTTPS origin,
for example `https://staging.example.com` or `https://example.com`. This keeps
links generated by cron and CLI on the correct scheme and host. Configure it
independently in every environment; do not copy a production origin into dev.

Editable templates support:

- `{{ticket_key}}`
- `{{subject}}`
- `{{customer_name}}`
- `{{customer_email}}`
- `{{support_name}}`
- `{{message}}`
- `{{ticket_url}}`

When `WireMailResend` is selected, Tickets uses its optional idempotency and
tagging APIs. Other providers use the standard ProcessWire WireMail contract.
Delivery failures are written to `site/assets/logs/tickets.txt` and never roll
back the ticket write.

Staff notification links open the authenticated admin ticket. Customer
notifications open the configured public portal and include a rotating private
access token only for guest tickets. The shared header and footer are configured
in **Modules → Tickets → Transactional mail**, are applied to every template,
and should use inline CSS for broad email-client support. Dangerous embedded
elements and inline event handlers are removed before sending.

`delivered_at` records acceptance by the configured outbound transport; it does
not claim inbox placement. `read_at` is recorded when the intended participant
opens the conversation in the staff workspace or customer portal. Consuming
portal templates should call:

```php
$tickets->markMessagesRead((int)$ticket['id'], $user, false);
```

### Resend inbound replies

Enable inbound replies, set the receiving address and webhook signing secret,
then route the Resend `email.received` webhook to:

```text
https://example.com/support/inbound/resend/
```

The consuming template passes the raw request body and normalized headers to:

```php
$result = $tickets->handleResendWebhook($rawBody, $headers);
```

The method validates the Svix signature, records `svix-id` for replay
protection, fetches the received email through Resend and accepts it only when
the sender matches the ticket customer.

### Mailbox ingestion and replies

The collapsed **Mailbox integration** settings section appears without creating a hard dependency. Inbound import can be enabled only when Mailbox is installed, has configured credentials, and background synchronization is enabled. Select one enabled account (or its default), an exact folder such as `INBOX`, and normally keep the support-recipient requirement enabled.

The lightweight `TicketsMailboxBridge` observes `Mailbox::messageIndexed` after the initial Mailbox seed. It fetches only the one announced message through the agent-safe DTO, ignores unrelated recipients and self-sent messages, creates a new ticket for a valid support request, or appends `[Ticket KEY]` replies after verifying the customer sender. Source rows make retries idempotent. It does not import historical messages, HTML, raw MIME, attachments, or executable links, and it does not ask AI to classify mail automatically.

For a reviewed bounded catch-up, preview the newest summary page with `php site/modules/Tickets/bin/tickets mailbox-import --limit=25 --root=/path/to/processwire`. The preview performs no ticket writes. Repeat with `--execute` to recognize and import at most 100 messages through the same deduplicated path; the command never expands to the whole mailbox.

Outbound Mailbox delivery is a separate option and also requires Tickets transactional mail plus Mailbox SMTP. Linked customer conversations use the original Mailbox source for SMTP reply threading when possible. Turning this option off restores the unchanged WireMail delivery path; Resend inbound handling remains independently available.

## Automation and SLA

Global first-response, resolution and automatic-close targets live in module
settings. First-match routing rules and reusable replies live under
**Setup → Tickets → Automation**.

Run the bounded worker from cron:

```bash
php site/modules/Tickets/bin/tickets automation --dry-run --root=/path/to/processwire
php site/modules/Tickets/bin/tickets automation --execute --root=/path/to/processwire
```

Repeated runs are idempotent. A five-minute interval is appropriate for normal
support queues.

Staff may extend the currently active first-response or resolution deadline by
a bounded interval from the ticket workspace. The previous breach marker is
cleared and the change is recorded in the ticket event log and rendered in the
staff Conversation timeline. Existing recorded extensions appear without a
data migration.

## Retention

Retention is disabled by default. Preview a bounded batch before enabling a
scheduled destructive run:

```bash
php site/modules/Tickets/bin/tickets retention --dry-run --root=/path/to/processwire
php site/modules/Tickets/bin/tickets retention --execute --root=/path/to/processwire
```

`anonymize` removes customer identity, private content, access tokens, context
and attachments while retaining aggregate workflow facts. `delete` permanently
removes the complete closed ticket and related records.

## Reply Assistance

When Squad is installed and configured, staff may explicitly request a draft.
Tickets can retrieve and deduplicate evidence from Atlas and Knowledge Base;
either source can be disabled or temporarily unavailable without disabling the
other. Customer email addresses and attachments are excluded from the prompt.

Every non-internal message is supplied in chronological order. For unusually
large threads, each message remains represented while its excerpt is reduced to
keep the total prompt bounded. Internal notes are never included. Drafts are
placed in the reply composer for staff review and are never sent automatically.
The separate **Fix writing** action corrects the current proposed reply using
the complete public conversation as context while preserving facts, links and
commitments. Provider credentials remain in Squad.

## GeoIP Context and Local Time

When the optional GeoIP module is installed, Tickets stores the resolved
country, region, city and valid IANA timezone at creation. Tickets does not add
a raw IP field. Account country and timezone are used as non-sensitive
fallbacks for authenticated customers and older tickets without a GeoIP
snapshot. Staff see the customer-local clock only when its timezone differs
from the configured support timezone; failures or unavailable GeoIP data never
block ticket creation.

## Permissions and Privacy

- `tickets-manage` grants staff ticket operations.
- `tickets-admin` grants module-level administration.
- Superusers always have both capabilities.
- Authenticated customers can access only their own `user_id` tickets.
- Guest access tokens are random, stored only as SHA-256 hashes and rotated on
  staff replies.
- Private files live under `site/assets/tickets/` with direct web access denied.
- Guest creation uses CSRF, consent, honeypot timing, cooldown and per-email
  rate limits.

## Frontend Frameworks

Tickets emits semantic HTML and provides role-based attributes for
Designsystemet, UIkit, Bootstrap, Tailwind, semantic HTML or a custom JSON role
map. Select the adapter in module settings and use `frontendAttributes()` in the
consuming template. The module does not require a specific public theme.

## Localization

English is the source language. Dutch, French, German, Italian and Spanish CSV
files are bundled in `languages/`. Install them through ProcessWire Language
Support; installed strings remain editable and missing translations fall back
to English.

## Uninstall Behavior

Uninstalling Tickets retains ticket tables, messages, attachments, permissions
and the public page. Remove retained data only through a deliberate,
site-specific cleanup with a verified backup.
