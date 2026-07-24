# EBizCharge Recurring Process

Last reviewed: 2026-07-22

## Scope and architecture

Recurring schedules are owned by Magento. EBizCharge does not create or run a gateway-side
recurring schedule for this module. Magento stores the subscription, creates a normal renewal
order on each billing date, and charges the customer's EBizCharge card profile through Magento
Vault.

The renewal call is `runCustomerTransaction` with the documented `custNum`, `paymentMethodID`, and
`tran` fields. Renewal orders set `tran.isRecurring=true`; ordinary saved-card checkouts set it to
`false`. `InventoryLocation` and `IgnoreDuplicate=false` are also present. See the EBizCharge
[`runCustomerTransaction`](https://developer.ebizcharge.net/connect/docs/runcustomertransaction)
and [`CustomerTransactionRequest`](https://developer.ebizcharge.net/connect/docs/customertransactionrequest)
documentation.

Only the gateway token `<CustNum>:<MethodID>` and masked card metadata are stored in Magento Vault.
Renewals never read or resend PAN or CVV.

## Eligibility and initial checkout

A subscription is created only when all of these conditions are true:

1. At least one visible order item has `gtstudio_subscribable=1` and a non-empty
   `gtstudio_subscription_frequency`.
2. The customer is signed in. Guest subscriptions are rejected before payment authorization.
3. The customer pays with an existing Gtstudio EBizCharge card or selects the Magento Vault
   save-card option for a new EBizCharge card.
4. The subscribable product is a simple product. Products that require reconstructing custom,
   bundle, configurable, or downloadable options are rejected until option snapshots are
   implemented.

Checkout validation runs on `sales_model_service_quote_submit_before`, before the initial gateway
authorization or sale. The initial order is charged normally. If a new card is being saved, the
approved transaction is followed by EBizCharge customer/profile provisioning and Magento's native
Vault persistence.

Subscription creation runs on `checkout_submit_all_after`, after the order payment and native Vault
token have been saved. Visible subscribable items are grouped by frequency, producing one
subscription per frequency. The first order counts as cycle 1.

For each item, Magento snapshots:

- product ID, SKU, quantity;
- discounted row price before tax, converted to a per-unit locked price;
- source order, customer, store, currency, token ID, frequency, and the next bill date.

The stored subscription amount is the locked recurring item subtotal. Tax and shipping are not
locked and are recalculated on the renewal order using current Magento configuration and customer
addresses.

If the initial payment is approved but optional card-profile provisioning fails, the approved order
remains valid. No unusable subscription is created, the payment records only
`subscription_creation_status=missing_vault_token`, and a redacted warning is logged. Operations
must resolve those orders manually if the customer still expects a subscription.

## Scheduling and charging

The recurring pipeline has two jobs in Magento's `default` cron group:

| Job | Cadence | Responsibility |
|---|---:|---|
| `gtstudio_ebizcharge_subscription_schedule` | Hourly | Find due `active` or `failing` subscriptions and create a pending charge row. |
| `gtstudio_ebizcharge_subscription_charge` | Every 15 minutes | Lock and process up to 50 pending charge rows. |

`scheduled_for` is the stable billing-cycle timestamp (`next_bill_date 00:00:00`), not the time the
hourly cron happened to run. Each attempt is a separate immutable row. The database unique key on
`(subscription_id, scheduled_for, attempt_count)` and an open-charge check prevent duplicate work.

For a pending charge, the engine:

1. Atomically changes `pending` to `in_progress`. Another worker cannot claim that row.
2. Loads the subscription and validates that the Magento Vault token is owned by the customer,
   active, visible, from the card provider, valid for the subscription website, unexpired, and has a
   usable `<CustNum>:<MethodID>` gateway token.
3. Restores the persisted correlation ID for the entire renewal request.
4. Builds a customer quote using the locked item quantity and custom price.
5. Uses current customer default addresses, falling back to the source order addresses. Physical
   orders use the cheapest currently available shipping rate.
6. Marks the quote payment as a renewal before order placement. This makes
   `runCustomerTransaction` send `isRecurring=true` and prevents the renewal order from creating a
   second subscription.
7. Places the quote through Magento's standard order and Vault command pipeline. Card line items
   are sent to EBizCharge; PAN and CVV are not.

## Success, failure, and retry behavior

On success:

- the charge becomes `succeeded`;
- the Magento renewal order ID and EBizCharge `RefNum` are stored on the charge row;
- `completed_cycles` increments and `failure_count` resets;
- `next_bill_date` advances from the billing schedule, not the cron execution time;
- monthly-family schedules retain the original day anchor and clamp to month end (for example,
  January 31 → February 28/29 → March 31);
- the subscription becomes `completed` at `max_cycles`, or `expired` when the next date is beyond
  `end_date`.

On failure:

- the attempt becomes `failed` and stores only a safe customer-facing error;
- `failure_count` increments;
- a separate pending attempt is queued for the next 15-minute charge-cron tick;
- the subscription becomes `failing` at the configured failing threshold (default 3) but retries
  continue;
- the subscription becomes `cancelled` at the cancel threshold (default 5), and no further retry is
  queued;
- the customer receives the configured failure notification. The email explains that Magento will
  retry and links to payment-method management.

Thresholds are read per store from:

- `payment/gtstudio_ebizcharge/subscription_failure_threshold_failing`
- `payment/gtstudio_ebizcharge/subscription_failure_threshold_cancel`

The cancel threshold must be greater than the failing threshold. Invalid configuration falls back
to safe defaults.

## Customer and administrator actions

- **Pause:** changes the subscription to `paused` and marks queued attempts `skipped`.
- **Resume:** permits `paused` or `failing`, restores `active`, resets failures, and makes an overdue
  bill date immediately eligible for scheduling.
- **Cancel:** permanently changes the subscription to `cancelled` and skips queued attempts.
- **Change payment method:** accepts only an accessible Gtstudio EBizCharge card token for the same
  customer and website. Updating a `failing` subscription restores it to `active` and resets the
  failure count; an already queued retry remains the next attempt.
- **Charge Now:** is available only for `active` subscriptions with no pending or in-progress
  charge. A successful manual charge advances the normal schedule, so it is not an extra charge
  outside the cycle.

Customer controls are under **My Account → My Subscriptions**. Administrator controls are under
**Sales → EBizCharge Subscriptions**. The detail view contains order IDs, gateway reference numbers,
correlation IDs, attempts, and redacted errors.

## Deployment and operational checks

Before enabling recurring cron in an installation that previously ran this module, audit old charge
rows created before the stable-cycle fix. In particular, inspect subscriptions with more than one
`pending`/`in_progress` row and reconcile any `in_progress` row against EBizCharge before changing
it. Do not blindly requeue it: the gateway may have approved a transaction before Magento stopped.

Suggested read-only audit:

```sql
SELECT subscription_id, status, COUNT(*) AS rows_count
FROM gtstudio_ebizcharge_subscription_charge
WHERE status IN ('pending', 'in_progress')
GROUP BY subscription_id, status
HAVING COUNT(*) > 1;
```

For every sandbox renewal, verify:

1. One renewal order and one successful charge row exist.
2. The SOAP trace uses `runCustomerTransaction` and contains `isRecurring=true`.
3. `custNum`, `paymentMethodID`, `ResultCode=A`, and `RefNum` are populated and redacted as
   appropriate.
4. The charge row stores the order ID, gateway reference, attempt number, and correlation ID.
5. No renewal order creates an additional subscription.
6. Database fields and logs contain no PAN, CVV, credentials, or unmasked profile data.

## Known limitations requiring follow-up

- Recurring card code is implemented and unit-tested but is not end-to-end sandbox certified.
- ACH recurring charges remain disabled/unvalidated.
- There is no gateway idempotency key. A process crash after gateway approval but before Magento
  order/charge persistence can leave an orphan approval and an `in_progress` row. Automatic stale
  attempt replay is intentionally not implemented until gateway reconciliation exists.
- Product option/configuration snapshots are not implemented; recurring checkout is restricted to
  simple products.
- Item prices are locked before tax, while tax, address eligibility, inventory, and shipping are
  evaluated again at renewal time. A changed or disabled product can therefore prevent renewal.
- Retry cadence is the 15-minute charge cron and is not independently configurable.
- `Charge Now` consumes and advances the next normal cycle.

