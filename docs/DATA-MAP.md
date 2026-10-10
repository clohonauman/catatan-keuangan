# Pemetaan Data Legacy ke MySQL

| Sumber legacy | Target MySQL | Catatan |
|---|---|---|
| `users.json.users[]` | `user` | ID, username, email, hash password/PIN, role, plan, verification/recovery metadata dipertahankan |
| `users.json.users[].device_tokens[]` | `user_device` | token disimpan sebagai hash seperti mekanisme lama |
| `user_finance/user_X.json.meta` | `finance_meta`, `offline_operation` | counter ID, revision, offline idempotency |
| `.settings` | `user_setting` | key/value JSON per user |
| `.wallets[]` | `wallet` | `legacy_id` menjaga ID wallet lama |
| `.categories[]` | `category` | `legacy_id` menjaga ID kategori lama |
| `.transactions[]` | `finance_transaction` | index per user/tanggal/type/wallet/kategori |
| `.chats[]` | `chat_message` | ID chat lama dipertahankan |
| `.monthly_budgets[]` | `monthly_budget` | composite key user/category/month |
| `.bills[]` | `bill` | termasuk payment history JSON |
| `.recurring[]` | `recurring_transaction` | jadwal dan next run |
| `.goals[]` | `saving_goal` | target/current/deadline |
| `.audit_log[]` | `audit_log` | before/after + undo status |
| `.pending_chat_confirmation` | `user_setting` internal | key internal `__pending_chat_confirmation` |
| `subscriptions.json.plans` | `subscription_plan` | package configuration |
| `.banks` | `payment_bank` | konfigurasi pembayaran |
| `.coupons` | `coupon` | expiry disimpan sebagai DATE agar semantik lama tetap sama |
| `.orders` | `subscription_order` | snapshot order tetap tersimpan pada `order_json` |
| `assistant_learning.json` | `app_document` | namespace `assistant/learning` |
| `admin_notifications.json` | `app_document` | namespace `admin/notifications` |
| `email_delivery_log.json` | `app_document` | namespace `email/delivery_log` |
| `privacy_requests.json` | `app_document` | namespace `privacy/requests` |
| `user_email_notifications.json` | `app_document` | namespace `email/notifications` |
| JSON root tambahan | `app_document` | namespace `legacy_archive` |
| `receipts/**` | `storage/private/receipts/**` | tidak public langsung |
| `payment_proofs/**` | `storage/private/payment_proofs/**` | tidak public langsung |

Semua tabel relasional memakai InnoDB + `utf8mb4` dan foreign key `CASCADE` untuk data milik user.
