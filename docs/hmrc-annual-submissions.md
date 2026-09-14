# HMRC annual submissions

This endpoint creates or amends the annual adjustments, allowances, and non-financial information for one self-employment business and tax year. Quarterly income and expenses remain in the quarterly update endpoint.

## Application endpoints

- `PUT /api/hmrc/annual-submission` submits or amends annual data.
- `GET /api/hmrc/annual-submissions` returns the authenticated user's submission history.

Both endpoints require the application's authenticated API user. The user must also have a current accepted HMRC authorisation.

## Sandbox configuration

Use this while checking that the request is accepted and recorded by the application:

```dotenv
HMRC_ENVIRONMENT=sandbox
HMRC_ANNUAL_TEST_SCENARIO=DEFAULT
```

`DEFAULT` lets HMRC validate the request shape without depending on previously created stateful test data. The application records a successful `DEFAULT` response as `simulated`, because it does not prove that HMRC saved the annual data.

Use this when testing against the stateful sandbox business previously created through `/api/hmrc/sandbox/business`:

```dotenv
HMRC_ANNUAL_TEST_SCENARIO=STATEFUL
```

After changing an environment value, run:

```bash
php artisan config:clear
```

## Example request

```http
PUT /api/hmrc/annual-submission
Authorization: Bearer APPLICATION_ACCESS_TOKEN
Content-Type: application/json
```

```json
{
  "business_id": "XBIS12345678901",
  "tax_year": "2026-27",
  "type_of_business": "self-employment",
  "payload": {
    "adjustments": {
      "includedNonTaxableProfits": 200.00,
      "goodsAndServicesOwnUse": 100.00
    },
    "allowances": {
      "annualInvestmentAllowance": 500.00
    }
  }
}
```

Send only values that apply. At least one value is required under `adjustments`, `allowances`, or `nonFinancials`. Do not send `periodDates`, `periodIncome`, or `periodExpenses`; those fields belong to the quarterly update.

Trading income allowance cannot be combined with another capital allowance:

```json
{
  "business_id": "XBIS12345678901",
  "tax_year": "2026-27",
  "type_of_business": "self-employment",
  "payload": {
    "allowances": {
      "tradingIncomeAllowance": 1000.00
    }
  }
}
```

The `business_id` must be the ID returned by HMRC for the same sandbox NINO and business. A placeholder ID normally produces `MATCHING_RESOURCE_NOT_FOUND` in the stateful scenario.

## Successful response

HMRC returns HTTP 204. The application returns a receipt similar to:

```json
{
  "success": true,
  "message": "Annual submission accepted by HMRC.",
  "data": {
    "submission_id": "APPLICATION_UUID",
    "status": "accepted",
    "correlation_id": "HMRC_CORRELATION_ID",
    "tax_year": "2026-27",
    "business_id": "XBIS12345678901"
  }
}
```

Keep the submission ID and correlation ID. The history endpoint exposes the receipt and status but does not expose the stored request payload.

## Deployment

Upload the changed Laravel files, then run:

```bash
php artisan migrate --force
php artisan optimize:clear
```

The migration creates `hmrc_annual_submissions`, which records every attempt before the HMRC call. The request payload is encrypted at rest.

