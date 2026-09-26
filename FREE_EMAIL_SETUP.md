# Free real-email delivery

The application now sends real transactional email through authenticated SMTP. It does not depend on PHP `mail()` or a local mail daemon.

## Free option A: Gmail SMTP

Use a dedicated Gmail account for the panel. Enable 2-Step Verification, create a Google App Password, and use the 16-character app password—not the normal account password.

```text
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=your-panel-mail@gmail.com
SMTP_PASS=your-16-character-app-password
SMTP_FROM_NAME=Royal
SMTP_FROM_EMAIL=your-panel-mail@gmail.com
SMTP_REPLY_TO=your-panel-mail@gmail.com
SMTP_USE_TLS=true
SMTP_TIMEOUT=15
```

Gmail documents `smtp.gmail.com` with port `587` for TLS or `465` for SSL. This implementation uses STARTTLS on port 587.

## Free option B: Brevo SMTP

Brevo provides a free SMTP relay allowance. Create and verify a sender, then create an SMTP key from the Brevo SMTP/API settings.

```text
SMTP_HOST=smtp-relay.brevo.com
SMTP_PORT=587
SMTP_USER=your-brevo-login-email
SMTP_PASS=your-brevo-smtp-key
SMTP_FROM_NAME=Royal
SMTP_FROM_EMAIL=verified-sender@example.com
SMTP_REPLY_TO=support@example.com
SMTP_USE_TLS=true
SMTP_TIMEOUT=15
```

`SMTP_FROM_EMAIL` must be a sender verified with the selected provider. Never commit `SMTP_PASS` to the repository.

## Messages sent

- Welcome email after registration
- Top-up success email after a confirmed balance credit
- Order accepted email after FastWay accepts an order
- Order status email when a synced order changes status, including provider cancellation/refund notices

Email failure is logged server-side and does not roll back a successful registration, payment, or order. This prevents mail-provider downtime from corrupting financial or provider state.

## Render setup

In the Render service environment, set the SMTP variables marked `sync: false` in `render.yaml`. Redeploy after saving them. The repository contains safe defaults and no credentials.

## Sources

- [Gmail: send email from an app](https://knowledge.workspace.google.com/admin/gmail/send-email-from-app)
- [Brevo: free SMTP server](https://www.brevo.com/free-smtp-server/)
- [Brevo: SMTP transactional email setup](https://help.brevo.com/hc/en-us/articles/7924908994450-Send-transactional-emails-using-Brevo-SMTP)
