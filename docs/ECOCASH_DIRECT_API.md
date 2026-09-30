# Norah Payment Gateway: Direct EcoCash API

This API lets your server trigger an EcoCash payment prompt on a customer's phone
without redirecting the customer to the Norah hosted checkout page. The customer
approves the payment on their handset; you learn the outcome by polling the status
endpoint or by receiving the webhook you have configured with Norah.

Every payment made this way is recorded against your merchant account exactly like a
hosted-checkout payment. It appears in your transaction history, your dashboards and
your payout statements with payment method `ECOCASH`.

## Base URL and authentication

All endpoints live under `https://<gateway-host>/api/v1`.

Obtain a bearer token first:

```
POST /merchant-sign-in
Content-Type: application/json

{ "email": "merchant@example.com", "password": "********" }
```

The response contains `token`. Send it on every call:

```
Authorization: Bearer <token>
```

Tokens expire after 60 minutes. Sign in again when you receive a 401.

## 1. Trigger a payment

```
POST /ecocash/pay
```

| Field               | Type    | Required | Notes                                                                                   |
|---------------------|---------|----------|-----------------------------------------------------------------------------------------|
| `amount`            | number  | yes      | Amount the customer owes you, before gateway charges. Minimum 0.01.                     |
| `currency`          | string  | yes      | Three-letter code, for example `USD` or `ZWG`. Must have charges configured.            |
| `phoneNumber`       | string  | yes      | Customer's EcoCash number. Spaces, dashes and `+` are stripped. Example `0771234567`.   |
| `customerReference` | string  | no       | Your own reference (invoice number, order id). Max 60 characters. Used for idempotency. |

Charges are calculated by the gateway from your configured charge tables. You cannot
supply them. The response tells you the charge and the total the customer is asked to pay.

Example request:

```
POST /api/v1/ecocash/pay
Authorization: Bearer <token>
Content-Type: application/json

{
  "amount": 10.00,
  "currency": "USD",
  "phoneNumber": "0771234567",
  "customerReference": "INV-10021"
}
```

Success response, HTTP 202:

```json
{
  "success": true,
  "status": "PENDING",
  "duplicate": false,
  "message": "EcoCash payment prompt sent. Ask the customer to approve it on their phone.",
  "trace": "e4dc3bcf-b244-4479-a660-f19a456edd37",
  "reference": "NPG_1790804451588",
  "amount": "10.00",
  "charge": "0.20",
  "totalAmount": "10.20",
  "currency": "USD",
  "phoneNumber": "263771234567",
  "customerReference": "INV-10021",
  "statusUrl": "https://<gateway-host>/api/v1/ecocash/status/e4dc3bcf-...",
  "shouldPoll": true,
  "pollIntervalMs": 5000
}
```

Keep `trace`. It identifies the payment in every later call and in the webhook.

Other responses:

| HTTP | Meaning                                                                                                 |
|------|---------------------------------------------------------------------------------------------------------|
| 200  | `duplicate: true`. A prompt for the same `customerReference` is still pending from the last 10 minutes. The existing `trace` is returned and no second prompt is sent. |
| 401  | Missing, expired or invalid token.                                                                      |
| 403  | The account is not a merchant or admin account.                                                         |
| 422  | Validation failed, or no charge is configured for this currency and amount. See `errors` or `message`.  |
| 429  | Rate limit exceeded. Slow down and retry.                                                               |
| 502  | EcoCash rejected or did not answer the request. A FAILED transaction is recorded. `message` explains.   |

## 2. Poll for the outcome

```
GET /ecocash/status/{trace}
```

Call this every 5 seconds until `shouldPoll` is `false`. The customer usually has
about two minutes to approve the prompt on their phone.

```json
{
  "success": true,
  "status": "COMPLETED",
  "trace": "e4dc3bcf-b244-4479-a660-f19a456edd37",
  "responseCode": "00",
  "message": "Transaction completed successfully.",
  "shouldPoll": false,
  "transaction": {
    "id": 38,
    "type": "PAYMENT",
    "status": "COMPLETED",
    "trace": "e4dc3bcf-...",
    "reference": "NPG_1790804451588",
    "credit_reference": "MP240930.1234.A12345",
    "customer_reference": "INV-10021",
    "amount": "10.00",
    "charge": "0.20",
    "currency": "USD",
    "payment_method": "ECOCASH",
    "response_code": "00",
    "error_message": null,
    "created_at": "2026-09-30T21:40:51.000000Z",
    "updated_at": "2026-09-30T21:40:51.000000Z"
  }
}
```

`status` is one of:

| Status      | `success` | What to do                                                                 |
|-------------|-----------|----------------------------------------------------------------------------|
| `PENDING`   | true      | Keep polling.                                                              |
| `COMPLETED` | true      | Payment received. `transaction.credit_reference` is the EcoCash reference. |
| `FAILED`    | false     | Customer declined, insufficient funds, or timeout. `message` explains.      |
| `CANCELLED` | false     | Payment was cancelled.                                                     |

Polling is safe to repeat. A completed payment is never finalized twice.

## 3. Webhook (recommended)

If a webhook URL is configured on your merchant account, the gateway POSTs a JSON
notification to it as soon as a payment reaches `COMPLETED` or `FAILED`, whether or not
you are polling. You do not need to poll at all if you rely on the webhook, although
polling is a useful fallback.

```json
{
  "status": "COMPLETED",
  "timestamp": "2026-09-30T21:40:51+00:00",
  "trace": "e4dc3bcf-...",
  "amount": "10.00",
  "currency": "USD",
  "payment_method": "ECOCASH",
  "reference": "NPG_1790804451588",
  "customer_reference": "INV-10021",
  "merchant_uid": "18caf0a6-...",
  "transaction": { "...": "full transaction record, same fields as the status endpoint" }
}
```

On `FAILED` the payload also carries `response_code` and `error_message`.
Respond with any 2xx status. Match notifications to your orders using `trace` or
`customer_reference`.

The gateway also reconciles unanswered payments on its own every five minutes, so a
payment that the customer approved late still completes and still triggers the webhook.

## Integration notes

- **Idempotency.** Always send a unique `customerReference` per order. Retrying the same
  reference within 10 minutes returns the pending payment instead of prompting the
  customer again.
- **Do not prompt repeatedly.** Wait for a terminal status before sending a new prompt to
  the same customer for the same order.
- **Amounts.** `amount` is what you receive before charges; `totalAmount` is what the
  customer is debited. Both are returned as strings with two decimals.
- **Phone numbers.** Local (`077...`) and international (`26377...`) formats are both
  accepted. Non-digit characters are removed.
- **Rate limits.** The default is 60 requests per minute per account across the two
  endpoints. Contact Norah if you need more.
- **Errors.** Every error response is JSON with `success: false` and a `message`.
