# Free real-email delivery through Brevo API

The application sends transactional email through Brevo's HTTPS API on port
443. It no longer opens an SMTP socket, so Render SMTP egress and authorized
SMTP-IP restrictions do not affect delivery.

## Brevo configuration

1. In Brevo, create or copy an **API key** from **SMTP & API → API keys**.
2. Do not use the SMTP key for this integration.
3. Verify the sender address in Brevo under **Transactional → Senders**.
4. Set these Render environment variables:

```text
BREVO_API_KEY=your-brevo-api-key
BREVO_API_URL=https://api.brevo.com/v3/smtp/email
BREVO_FROM_NAME=Royal
BREVO_FROM_EMAIL=your-verified-brevo-sender@example.com
BREVO_REPLY_TO=your-verified-brevo-sender@example.com
BREVO_API_TIMEOUT=8
```

`BREVO_FROM_EMAIL` must exactly match a verified Brevo sender. Keep
`BREVO_API_KEY` in Render's secret environment settings and never commit it.

## Messages sent

- Welcome email after registration
- Top-up success email after a confirmed balance credit
- Order accepted email after FastWay accepts an order
- Order status email when a synced order changes status, including provider
  cancellation/refund notices

The application logs the Brevo HTTP status and returned message ID without
logging the API key. A Brevo message ID means Brevo accepted the request; final
Inbox/Spam delivery status is available in **Brevo → Transactional → Logs**.
Email failure does not roll back a successful registration, payment, or order.

## Testing

1. Save the Render variables.
2. Trigger a new registration using an accessible email address.
3. Open **Brevo → Transactional → Logs** immediately.
4. Check Render logs for either:
   - `Brevo accepted transactional email: ...`
   - `Brevo email rejected HTTP ...`
   - `Brevo HTTPS email request failed: ...`
5. Check the recipient Inbox and Spam folders.

## Official reference

- [Brevo: Send a transactional email](https://developers.brevo.com/docs/send-a-transactional-email)
