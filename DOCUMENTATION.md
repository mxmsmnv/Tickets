# Tickets Documentation

This document describes the integration contract implemented by Tickets 1.0.9
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

## Operational API and CLI

Tickets exposes three independent operational channels. Fresh installations
and upgrades keep all of them disabled:

- the PHP facade from `$tickets->api($actor)`;
- same-origin JSON REST under `/tickets-api/v1/`;
- the local `site/modules/Tickets/bin/tickets` executable.

The PHP and REST channels require a logged-in actor with both `tickets-api` and
`tickets-manage`. Report and form-definition reads additionally require
`tickets-admin`. REST uses the existing ProcessWire session, deliberately emits
no CORS headers, and requires the CSRF token returned by
`GET /tickets-api/v1/session/` on every POST.

Available REST resources are `session`, `capabilities`, `dashboard`, `queue`,
`ticket`, `messages`, `report`, `forms`, `update`, `reply`, and `note`. Responses
use either `{"ok":true,"result":...}` or `{"ok":false,"error":"..."}`.
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

Drafts are placed in the reply composer for staff review and are never sent
automatically. Provider credentials remain in Squad.

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
