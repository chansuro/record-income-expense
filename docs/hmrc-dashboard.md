# HMRC mobile dashboard

## Endpoint

```http
GET /api/hmrc/dashboard?business_id=XBIS12345678901&type_of_business=self-employment&tax_year=2026-27
Authorization: Bearer APPLICATION_ACCESS_TOKEN
Accept: application/json
```

`tax_year` selects the assessment year and must use the `YYYY-YY` format, for example `2026-27`. `business_id`, `type_of_business`, `date`, `status`, and `reporting_period` are optional. Supplying the selected HMRC business is recommended when a customer has more than one business.

`date` remains available as an optional as-of date. When `tax_year` is omitted, the endpoint keeps its previous behaviour and infers the assessment year from `date` or today's date.

When `status=open` or `status=fulfilled` is supplied, the original dashboard fields remain filtered for backward compatibility. `dashboard_extensions.next_due` and `dashboard_extensions.year_progress` are calculated from an additional all-status obligations request, so fulfilled quarters and the next open deadline can appear together.

## Backward-compatible response

The existing fields remain directly under `data`: `businesses`, `quarters`, `current_quarter`, `due_date`, `due_in_days`, `next_obligation`, `tax_year`, `tax_year_range`, `assessment_year`, `as_of_date`, `status_filter`, and `tax_details`. Existing app releases can continue decoding the same structure.

All new fields are contained under `data.dashboard_extensions`:

- `connection`: HMRC connection status and environment.
- `business`: the selected business identity and trading information.
- `mtd_status`: HMRC's ITSA status response for the selected tax year.
- `obligations_source`: normally `hmrc`; `hmrc_sandbox_dynamic_without_business_filter` means HMRC's sandbox DYNAMIC fixture required a sandbox-only retry without the selected business filter.
- `financial_period`: date range used for HMRC balances and payment allocations. It is derived from `tax_year` and covers two complete assessment years, staying within HMRC's 732-day maximum.
- `next_due`: a smaller mobile-friendly form of the nearest open quarterly update.
- `year_progress`: submitted count, total count, and percentage.
- `figures`: typed numeric versions of the existing local estimates.
- `account_balances`: official HMRC payable, pending, overdue, and credit balances.
- `tax_and_payments`: the POA scenario, prior POAs, balancing position, next-year POAs, and total remaining amount.
- `data_availability`: identifies each HMRC source that responded successfully.

There is no newly added upcoming-obligations collection. The existing `next_obligation` field is retained for backward compatibility.

## POA scenarios

- `no_previous_poa` (`A`): no POA has been paid toward the selected year.
- `poa_insufficient` (`B`): POA paid is less than the current estimated liability.
- `poa_overpaid` (`C`): POA paid is greater than the current estimated liability.
- `poa_covers_liability` (`D`): POA paid equals the current estimated liability.
- `data_unavailable`: HMRC account data could not be loaded, so the app must not display a POA conclusion.

`estimated_credit` is not a confirmed refund. Use `account_balances.available_credit` for HMRC's official account credit and explain that HMRC may refund it or allocate it to another liability.

## Partial responses

The dashboard returns the sections that are available when one HMRC service fails. When `dashboard_extensions.partial` is `true`, inspect `dashboard_extensions.data_availability` and `dashboard_extensions.errors`. For example, if account data is unavailable, the quarterly progress and local figures remain usable but `dashboard_extensions.tax_and_payments.scenario` becomes `data_unavailable`.

## Amount sources

The `figures` and `estimated_liability` values are local estimates. POA charges, payment allocations, and account balances come from HMRC. The response includes `source` and `data_quality` fields so the mobile interface can label estimates correctly.
