# Quarterly update deployment and testing

The endpoint is for self-employment cumulative quarterly updates from 2025-26 onwards.
It does not calculate your income/expenses from transactions. Review the cumulative totals before submitting.
Annual/latent submissions without reporting dates and property submissions need separate flows.

## Deploy

Deploy the changed controller, routes and config together with these new classes and migration:

- `app/Http/Requests/SubmitHmrcQuarterlyUpdateRequest.php`
- `app/Exceptions/HmrcSubmissionException.php`
- `app/Models/HmrcQuarterlySubmission.php`
- `app/Services/HmrcFraudHeaders.php`
- `app/Services/HmrcService.php`
- `app/Http/Controllers/HmrcMtdController.php`
- `config/services.php`
- `routes/api.php`
- `database/migrations/2026_09_11_120000_create_hmrc_quarterly_submissions_table.php`

Keep any previously added dashboard service deployed as well.

Run on the server from the Laravel project directory:

```bash
php artisan migrate --path=database/migrations/2026_09_11_120000_create_hmrc_quarterly_submissions_table.php --force
php artisan config:clear
php artisan route:clear
```

Apply your normal config/route cache build and PHP worker reload afterwards if your deployment uses them.
The migration adds a table; no existing tax records are changed. It must run before submissions can succeed.
Preserve the Laravel APP_KEY: payload snapshots are encrypted with it.

## Sandbox

```dotenv
HMRC_ENV=sandbox
HMRC_BASE_URL=https://test-api.service.hmrc.gov.uk
HMRC_QUARTERLY_TEST_SCENARIO=STATEFUL
```

The test client needs accepted agent authorisation, a seeded ITSA status and a self-employment test business
created with HMRC's **Create a Test Business** endpoint for the same NINO. Use that business's ID.
The fixed `XBIS12345678901` from DYNAMIC obligations is not evidence that a stateful test business exists.
STATEFUL supports standard and calendar reporting; HMRC applies business/date rules, including early submission rules.

Send the customer application token, not an HMRC token:

```http
PUT /api/hmrc/quarterly-update
Authorization: Bearer <customer application token>
Accept: application/json
Content-Type: application/json
```

```json
{
  "business_id": "<ID returned for your seeded self-employment business>",
  "tax_year": "2026-27",
  "payload": {
    "periodDates": {
      "periodStartDate": "2026-04-06",
      "periodEndDate": "2026-07-05"
    },
    "periodIncome": { "turnover": 15000, "other": 0 },
    "periodExpenses": { "costOfGoods": 2300.50, "adminCosts": 100 }
  }
}
```

Use JSON numbers (not quoted amounts), at most two decimal places. Zero values are valid.
Include income and expenses even when zero. Expense adjustments may be negative where allowed by HMRC.
Do not combine consolidated expenses with itemised or disallowable expenses. Check eligibility before using consolidated expenses.
The server validates field names, number formats, dates and tax year. HMRC remains responsible for checking
the actual business, reporting basis, commencement date, eligibility and submission timing.
Use HMRC's cumulative `periodStartDate`/`periodEndDate`, not the dashboard's individual-quarter `start`/`end`.

Only HTTP 204 counts as success. Successful responses include `submission_id`, `correlation_id`, `environment`,
`test_scenario`, `hmrc_http_status` and `status`.

`HMRC_QUARTERLY_TEST_SCENARIO=DEFAULT` is available for static testing but records `status=simulated`.
It does not confirm a stored update. Do not pass DYNAMIC to this API; that is an obligations scenario.
The DYNAMIC obligations list does not automatically become fulfilled after a stateful write.

## History and uncertain outcomes

```http
GET /api/hmrc/quarterly-submissions?business_id=<business ID>&tax_year=2026-27
Authorization: Bearer <same customer application token>
```

History is paginated and restricted to the current customer and environment. Payload snapshots are encrypted
in storage and omitted from this list. Neither NINOs nor OAuth tokens are stored in the submission table.

- `pending`: a record was created before the request. A crashed process may leave this state; reconcile it.
- `accepted`: HMRC returned 204 (in the environment named on the record).
- `simulated`: a static sandbox scenario returned 204.
- `rejected`: HMRC returned a 4xx error; inspect the HMRC code and correlation ID.
- `unknown`: a timeout, unexpected response or server error means acceptance cannot be established.
- `not_sent`: processing failed before dispatch, for example missing production collection configuration.

Never automatically retry pending/unknown writes. Retrieve the cumulative summary from HMRC using its
**Retrieve a Self-Employment Cumulative Period Summary** API (STATEFUL when testing) or investigate with its
correlation ID before deciding whether to resend. A repeat PUT replaces cumulative values; it must contain
the complete reviewed totals. This application does not yet expose the HMRC summary-retrieval endpoint.
If HMRC accepted but the local receipt update fails, the response still reports acceptance with
`history_saved=false`; retain the response and reconcile the pending history record instead of submitting again.

## Production fraud prevention: integration required

The new quarterly flow refuses production writes when fraud-prevention collection is unconfigured.
It does not fabricate device data, public IP/port, authentication factors or licensing data.
This does not complete the existing empty fraudHeaders helper used by other HMRC methods.

Configure the actual client type, product and version:

```dotenv
HMRC_FRAUD_CONNECTION_METHOD=MOBILE_APP_VIA_SERVER
HMRC_FRAUD_PRODUCT_NAME=<marketed product name>
HMRC_FRAUD_VENDOR_VERSION=<percent-encoded software=version pairs>
```

WEB_APP_VIA_SERVER is also supported. Do not select a method solely to make validation pass.
The client sends `fraud_prevention` alongside `payload`, with HMRC-formatted strings for:

- Gov-Client-Device-ID (persistent UUID), Gov-Client-Screens, Gov-Client-Timezone, Gov-Client-Window-Size.
- Web: Gov-Client-Browser-JS-User-Agent.
- Mobile: Gov-Client-User-Agent, Gov-Client-Local-IPs, Gov-Client-Local-IPs-Timestamp.

All keys must be present. Some unavailable fields can be explicitly empty as required by HMRC's missing-data
guidance; do not use invented values. This builder checks identity, control characters and basic structure,
but HMRC's Fraud Prevention Headers Test API must validate the actual deployment's complete formatting.

For proxied deployments, trusted server middleware must populate the request attribute `hmrc_fraud_server_context` with:

- Gov-Client-Public-IP, Gov-Client-Public-IP-Timestamp, Gov-Client-Public-Port.
- Gov-Vendor-Public-IP, Gov-Vendor-Forwarded (the complete real public TLS hop chain).
- Gov-Client-Multi-Factor and Gov-Vendor-License-IDs from actual authentication/licensing records.

Never populate that attribute from arbitrary incoming headers or client JSON. With a proxy/WAF/CDN, configure
trusted proxy boundaries and collect the originating port/time and hop chain at the trusted edge. REMOTE_PORT
at the backend may be the proxy's port, not the customer's. This deployment-specific middleware is not yet
implemented: confirm whether taxitax.uk uses Cloudflare or another proxy before connecting it.

For the confirmed mobile-app/direct-server deployment, set `HMRC_FRAUD_DIRECT_CONNECTION=true`,
`HMRC_FRAUD_CONNECTION_METHOD=MOBILE_APP_VIA_SERVER`,
and `HMRC_FRAUD_VENDOR_PUBLIC_IP` to the receiving server's actual public IP. The builder collects network facts
from the request server variables. This application currently authenticates using a password only and has no
device software licence, so it sends the required MFA and licence headers with empty values to report unavailable data.
The authenticated user ID and login are derived on the server.

## HMRC references

- [Submission specification](https://raw.githubusercontent.com/hmrc/self-employment-business-api/main/resources/public/api/conf/5.0/create_amend_cumulative_period_summary.yaml)
- [Payload schema](https://raw.githubusercontent.com/hmrc/self-employment-business-api/main/resources/public/api/conf/5.0/schemas/createAmendCumulativePeriodSummary/request.json)
- [Mobile fraud headers](https://developer.service.hmrc.gov.uk/guides/fraud-prevention/connection-method/mobile-app-via-server/)
- [Web fraud headers](https://developer.service.hmrc.gov.uk/guides/fraud-prevention/connection-method/web-app-via-server/)
