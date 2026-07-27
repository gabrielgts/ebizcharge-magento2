# Gtstudio EBizCharge for Magento 2

[![Module version](https://img.shields.io/badge/module-1.0.2-1f6feb)](composer.json)
[![Magento](https://img.shields.io/badge/Magento-2.x-EE672F?logo=magento&logoColor=white)](https://business.adobe.com/products/magento/magento-commerce.html)
[![PHP](https://img.shields.io/badge/PHP-8.3%20%7C%208.4-777BB4?logo=php&logoColor=white)](composer.json)
[![Unit tests](https://img.shields.io/badge/unit_tests-128_passing-2ea44f)](#testing-and-code-coverage)
[![Line coverage](https://img.shields.io/badge/line_coverage-20.91%25-d29922)](#testing-and-code-coverage)
[![License](https://img.shields.io/badge/license-MIT-2ea44f)](LICENSE)

`Gtstudio_Ebizcharge` is a server-side EBizCharge Connect payment integration for Magento 2 and
Adobe Commerce. It replaces the legacy `ebizcharge/ebizcharge` package while following Magento
payment gateway and Vault patterns.

## Capabilities

- Credit-card authorization, sale, capture, partial capture, refund, partial refund, and void.
- Payment Action support: **Authorize Only** sends `authonly`; **Authorize and Capture** sends
  `sale`.
- Magento Vault saved-card storage using EBizCharge customer payment profiles.
- EBizCharge customer identity mapping for ERP transaction association.
- Magento catalog line items in initial authorization and sale requests.
- Magento-driven recurring billing using customer-owned Vault tokens.
- ACH payment scaffolding and legacy card/ACH token migration.
- Redacted SOAP diagnostics, credential verification, and correlation metadata.

The module does not implement ERP capture policy. Magento's configured Payment Action remains the
only authority for authorization versus immediate capture.

## Requirements

| Component | Requirement |
|---|---|
| Magento | Magento Open Source or Adobe Commerce 2.x |
| PHP | `~8.3.0` or `~8.4.0` |
| PHP extensions | SOAP, OpenSSL, JSON, DOM, and the extensions required by Magento |
| Magento modules | Customer, Payment, Sales, Checkout, Vault, Store, Config, and Quote |
| Gateway | EBizCharge Connect sandbox or production credentials |
| Scheduling | Magento cron for subscriptions, notifications, and trace retention |
| Currency | USD is the configured gateway default |

Composer package: `gtstudio/module-ebizcharge`

Magento module: `Gtstudio_Ebizcharge`

Current module version: `1.0.2`

## Installation

### Composer installation

Composer is the recommended installation method. Ensure
`gtstudio/module-ebizcharge` is available through an authorized Composer repository, such as
Private Packagist, Satis, an authenticated VCS repository, or the project's package repository.
Do not commit repository credentials or access tokens to source control.

From the Magento root:

```bash
composer require gtstudio/module-ebizcharge:^1.0
bin/magento setup:upgrade
```

Composer installs the package according to its `magento2-module` package type and PSR-4
autoloading metadata. Commit the resulting `composer.json` and `composer.lock` changes according
to the project's dependency-management policy.

If the package is hosted in a private VCS repository that is not already registered in the
project, configure that repository before running `composer require`:

```bash
composer config repositories.gtstudio-ebizcharge vcs <authorized-repository-url>
composer require gtstudio/module-ebizcharge:^1.0
```

Use Composer authentication mechanisms or deployment environment secrets for private
credentials; do not place credentials directly in `composer.json`.

### Manual installation

When a Composer package repository is unavailable, place the module source in
`app/code/Gtstudio/Ebizcharge`, then run:

```bash
bin/magento module:enable Gtstudio_Ebizcharge
bin/magento setup:upgrade
bin/magento cache:flush
```

Composer installation should remain the default for repeatable deployments and dependency
tracking. For production mode, regenerate dependency injection and static assets according to
the deployment process used by the Magento installation.

Configure the integration under:

`Stores > Configuration > Sales > Payment Methods > EBizCharge (Gtstudio)`

Configure sandbox credentials first, use **Verify Credentials**, select the required Payment
Action, and enable the payment method only after an approved and declined transaction have both
been validated.

## Customer identity and Vault

Registered customers are associated with three distinct EBizCharge identifiers:

| Identifier | Purpose |
|---|---|
| `CustomerId` | Magento/ERP-facing ID sent as `tran.CustomerID` in initial transactions |
| `CustomerInternalId` | EBizCharge-generated ID used by customer and profile APIs |
| `CustNum` | EBizCharge customer number used by saved-card transactions |

Mappings are stored in the module-owned `gtstudio_ebizcharge_customer_identity` table. The module
does not add columns to Magento's `customer_entity` table. Existing legacy `ec_cust_*` values are
copied into the module table by a data patch when those columns are present.

See [Customer Identity Process](docs/CUSTOMER_IDENTITY.md) for checkout, Vault, Admin, CLI, ERP,
and migration details.

## Operational commands

```bash
# Verify configured credentials and connectivity
bin/magento gtstudio:ebizcharge:probe

# Read-only verification of one customer mapping
bin/magento gtstudio:ebizcharge:customer:check <customer-id> --store-id=<store-id>

# Resolve or create and persist customer mappings
bin/magento gtstudio:ebizcharge:customer:sync <customer-id> --store-id=<store-id>
bin/magento gtstudio:ebizcharge:customer:sync --all --store-id=<store-id>

# Existing legacy Vault-token migration; dry-run unless --execute is supplied
bin/magento gtstudio:ebizcharge:vault:migrate
bin/magento gtstudio:ebizcharge:vault:migrate --execute

# Legacy subscription migration; dry-run unless --execute is supplied
bin/magento gtstudio:ebizcharge:subscription:migrate-legacy
bin/magento gtstudio:ebizcharge:subscription:migrate-legacy --execute
```

Customer lookup, creation, and customer-number resolution are centralized in one identity
service. The migration, checkout, Vault, Admin, and CLI paths reuse that service rather than
maintaining separate implementations.

There is no Magento setup data patch for legacy Vault migration. `setup:upgrade` never contacts
EBizCharge or scans remote payment profiles. Migration is intentionally operator-initiated through
the command above. On large datasets, run the read-only command during a maintenance window before
using `--execute`.

## Security

- Gateway credentials use Magento's encrypted configuration backend.
- PAN and CVV are held only in a request-local handoff and are not stored in order payment
  additional information or Vault tokens.
- Magento Vault stores only the EBizCharge `<CustNum>:<MethodID>` token and masked card metadata.
- SOAP logs and debug traces pass through recursive credential, PAN, CVV, and bank-data
  redaction.
- Vault tokens are checked for provider, activity, customer ownership, website access, and
  expiration before a saved-card request is sent.
- Remote payment-profile deletion is best effort; local Magento token deletion remains
  authoritative.

PCI scope and production suitability must still be reviewed for the complete hosting,
administrative, logging, and checkout environment.

## Testing and code coverage

Baseline measured on 2026-07-27 with PHP 8.4.6, PHPUnit 10.5.63, and Xdebug 3.4.2:

| Metric | Result |
|---|---:|
| Passing unit tests | 128 |
| Assertions | 291 |
| Line coverage | 20.91% (687 / 3,286) |
| Method coverage | 11.94% (53 / 444) |
| Class coverage | 9.48% (11 / 116) |

The coverage baseline excludes `AchDataBuilderTest`. Its four tests currently stop during mock
configuration because `unsAdditionalInformation` is not declared on the PHPUnit mock. The full
suite therefore reports 132 tests, 291 assertions, and those four known test-harness errors; no
additional failures are present.

Run the full unit suite:

```bash
vendor/bin/phpunit -c app/code/Gtstudio/Ebizcharge/Test/Unit/phpunit.xml \
    --do-not-cache-result
```

Reproduce the published coverage baseline:

```bash
XDEBUG_MODE=coverage vendor/bin/phpunit \
    -c app/code/Gtstudio/Ebizcharge/Test/Unit/phpunit.xml \
    --do-not-cache-result \
    --filter '/^(?!.*AchDataBuilderTest)/' \
    --coverage-text
```

Coverage is a measured baseline, not a release-quality threshold. Critical payment paths should
also be validated with approved and declined cards against EBizCharge sandbox credentials.

## Validation status and limitations

- New-card authorization and sale request paths have focused unit coverage.
- Customer identity, response normalization, line items, Vault contracts, token deletion,
  recurring validation, and sensitive-data redaction have focused unit coverage.
- Card Vault, ACH, subscription migration, and recurring billing are implemented. The recurring
  saved-card authorization path was sandbox-validated on 2026-07-27; the remaining recurring
  scenarios still require complete end-to-end validation for the target deployment.
- ACH has four known PHPUnit mock errors described above.
- General transaction idempotency and orphan-authorization reconciliation remain follow-up work.
- Magento Admin Vault order creation is not enabled.

See [Recurring Process](docs/RECURRING_PROCESS.md) and the project-level
[implementation plan](../../../../GTSTUDIO_EBIZCHARGE_PLAN.md) for the detailed implementation
audit and deferred validation work.

## License and warranty

This module is released under the [MIT License](LICENSE).

The software is provided **as is**, without warranty or guarantee of any kind, express or implied,
including merchantability, fitness for a particular purpose, and noninfringement. The authors and
copyright holders are not liable for claims, damages, payment losses, service interruptions, data
loss, or other liability arising from the software or its use.

The MIT license applies to the module source code. It does not grant rights to Magento, Adobe,
EBizCharge, third-party libraries, trademarks, gateway services, or merchant credentials. Each
deployment remains responsible for security review, PCI obligations, gateway certification,
sandbox testing, backups, monitoring, and production acceptance.
