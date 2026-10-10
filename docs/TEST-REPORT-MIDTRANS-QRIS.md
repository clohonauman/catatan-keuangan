# Test Report — Premium QRIS Midtrans

Status: **local smoke tests passed; provider Sandbox/live transaction not executed** (merchant Server Key was not provided to the build environment).

## Automated local checks

Command:

```bash
php tests/smoke/midtrans_qris_smoke.php
```

Result: **20 checks passed**, including:

- test-mode configuration enables QRIS with a non-placeholder test key;
- valid notification signature accepted;
- amount tampering and invalid signature rejected;
- official Midtrans QR image URLs accepted, including QR v2 and ASPI v4 response shapes;
- `http://`, non-Midtrans domains, and unexpected paths rejected;
- provider QR image URLs normalized to the host selected by `MIDTRANS_IS_PRODUCTION`;
- placeholder Server Key cannot enable QRIS;
- Yii webhook, signature guard, settlement/amount checks, invoice action, UI option, and Service Worker v253 wiring present.

PHP syntax checks passed for:

```text
controllers/PaymentController.php
services/MidtransQrisService.php
legacy/subscription_helper.php
legacy/ajax/subscription.php
views/site/index.php
tests/smoke/midtrans_qris_smoke.php
```

JavaScript syntax checks passed for:

```text
web/assets/app.js
web/sw.js
```

The environment did not have the `composer` executable, so `composer validate` could not be run. This integration adds no Composer package.

## Required provider Sandbox test after installation

Before accepting real payments, use a Sandbox Server Key, verify QR generation, complete a test payment through the Midtrans QRIS simulator, confirm that the public notification endpoint receives a valid `settlement`, and deliver the same notification twice to verify Premium activates only once. Test invalid signature, wrong amount, and expiration do not activate Premium. Follow [`MIDTRANS-QRIS.md`](MIDTRANS-QRIS.md).
