# Tickets end-to-end fixture

This fixture exercises Tickets in a disposable ProcessWire installation with
network-free Mailbox and TeleWire doubles. Never point it at a site that contains
data worth keeping: `cleanup.php` removes the Tickets schema, support page,
permissions, fixture modules, test account, files, and logs.

Link the repository and fixtures into the disposable site, then run:

```sh
php tests/e2e/setup.php /path/to/processwire
php tests/e2e/verify.php /path/to/processwire
php -S 127.0.0.1:8766 -t /path/to/processwire tests/e2e/fixtures/router.php
```

In a browser, submit `/support/`, sign in at `/processwire/` as
`e2e-tickets-admin` with the fixture-only password
`Tickets-E2E-only-2026!`, and inspect `/processwire/setup/tickets/`. Verify the
queue, imported conversation, private attachment route, notification-event
checkboxes, and desktop/mobile layouts. Then run the post-browser assertion:

```sh
php tests/e2e/browser-verify.php /path/to/processwire
```

Stop the local server, run `cleanup.php`, and remove only the fixture symlinks
created for the run.
