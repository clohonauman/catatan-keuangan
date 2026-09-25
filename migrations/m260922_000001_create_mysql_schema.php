<?php

use yii\db\Migration;

final class m260922_000001_create_mysql_schema extends Migration
{
    public function safeUp()
    {
        $opt = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';

        $this->createTable('{{%user}}', [
            'id' => $this->primaryKey()->unsigned(),
            'username' => $this->string(30)->notNull(),
            'email' => $this->string(190)->null(),
            'password_hash' => $this->string(255)->notNull(),
            'pin_hash' => $this->string(255)->null(),
            'role' => $this->string(30)->notNull()->defaultValue('user'),
            'plan' => $this->string(30)->notNull()->defaultValue('free'),
            'plan_expires_at' => $this->dateTime()->null(),
            'premium_type' => $this->string(50)->null(),
            'premium_last_invoice' => $this->string(80)->null(),
            'email_verified_at' => $this->dateTime()->null(),
            'email_verification_token_hash' => $this->string(64)->null(),
            'email_verification_expires_at' => $this->dateTime()->null(),
            'email_verification_sent_at' => $this->dateTime()->null(),
            'email_verification_attempts' => $this->integer()->notNull()->defaultValue(0),
            'recovery_token_hash' => $this->string(64)->null(),
            'recovery_token_expires_at' => $this->dateTime()->null(),
            'recovery_token_sent_at' => $this->dateTime()->null(),
            'recovery_token_attempts' => $this->integer()->notNull()->defaultValue(0),
            'password_reset_attempts' => $this->integer()->notNull()->defaultValue(0),
            'password_reset_locked_until' => $this->dateTime()->null(),
            'pin_changed_at' => $this->dateTime()->null(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
            'extra_json' => $this->text()->null(),
        ], $opt);
        $this->createIndex('ux_user_username', '{{%user}}', 'username', true);
        $this->createIndex('ux_user_email', '{{%user}}', 'email', true);
        $this->createIndex('ix_user_role_plan', '{{%user}}', ['role','plan']);

        $this->createTable('{{%user_device}}', [
            'id' => $this->bigPrimaryKey()->unsigned(),
            'user_id' => $this->integer()->unsigned()->notNull(),
            'public_id' => $this->string(40)->notNull(),
            'token_hash' => $this->string(64)->notNull(),
            'device_name' => $this->string(190)->null(),
            'os' => $this->string(80)->null(),
            'browser' => $this->string(100)->null(),
            'user_agent' => $this->text()->null(),
            'last_ip' => $this->string(45)->null(),
            'created_at' => $this->dateTime()->notNull(),
            'last_used_at' => $this->dateTime()->notNull(),
        ], $opt);
        $this->addForeignKey('fk_device_user', '{{%user_device}}', 'user_id', '{{%user}}', 'id', 'CASCADE');
        $this->createIndex('ux_device_hash', '{{%user_device}}', 'token_hash', true);
        $this->createIndex('ux_device_user_public', '{{%user_device}}', ['user_id','public_id'], true);
        $this->createIndex('ix_device_user_last', '{{%user_device}}', ['user_id','last_used_at']);

        $this->createTable('{{%finance_meta}}', [
            'user_id' => $this->integer()->unsigned()->notNull(),
            'revision' => $this->bigInteger()->unsigned()->notNull()->defaultValue(0),
            'next_transaction_id' => $this->integer()->unsigned()->notNull()->defaultValue(1),
            'next_chat_id' => $this->integer()->unsigned()->notNull()->defaultValue(1),
            'next_wallet_id' => $this->integer()->unsigned()->notNull()->defaultValue(2),
            'next_category_id' => $this->integer()->unsigned()->notNull()->defaultValue(14),
            'next_bill_id' => $this->integer()->unsigned()->notNull()->defaultValue(1),
            'next_recurring_id' => $this->integer()->unsigned()->notNull()->defaultValue(1),
            'next_goal_id' => $this->integer()->unsigned()->notNull()->defaultValue(1),
            'next_audit_id' => $this->integer()->unsigned()->notNull()->defaultValue(1),
            'next_confirmation_id' => $this->integer()->unsigned()->notNull()->defaultValue(1),
            'extra_json' => $this->text()->null(),
            'updated_at' => $this->dateTime()->notNull(),
            'PRIMARY KEY ([[user_id]])',
        ], $opt);
        $this->addForeignKey('fk_meta_user', '{{%finance_meta}}', 'user_id', '{{%user}}', 'id', 'CASCADE');

        $this->createTable('{{%user_setting}}', [
            'user_id' => $this->integer()->unsigned()->notNull(),
            'setting_key' => $this->string(100)->notNull(),
            'value_json' => $this->text()->null(),
            'updated_at' => $this->dateTime()->notNull(),
            'PRIMARY KEY ([[user_id]], [[setting_key]])',
        ], $opt);
        $this->addForeignKey('fk_setting_user', '{{%user_setting}}', 'user_id', '{{%user}}', 'id', 'CASCADE');

        $this->createTable('{{%wallet}}', [
            'id' => $this->bigPrimaryKey()->unsigned(),
            'user_id' => $this->integer()->unsigned()->notNull(),
            'legacy_id' => $this->integer()->unsigned()->notNull(),
            'name' => $this->string(80)->notNull(),
            'type' => $this->string(30)->notNull(),
            'initial_balance' => $this->bigInteger()->notNull()->defaultValue(0),
            'reserved_balance' => $this->bigInteger()->notNull()->defaultValue(0),
            'minimum_balance' => $this->bigInteger()->notNull()->defaultValue(0),
            'archived' => $this->boolean()->notNull()->defaultValue(false),
            'created_at' => $this->dateTime()->null(),
            'extra_json' => $this->text()->null(),
        ], $opt);
        $this->addForeignKey('fk_wallet_user', '{{%wallet}}', 'user_id', '{{%user}}', 'id', 'CASCADE');
        $this->createIndex('ux_wallet_user_legacy', '{{%wallet}}', ['user_id','legacy_id'], true);
        $this->createIndex('ix_wallet_user_archived', '{{%wallet}}', ['user_id','archived']);

        $this->createTable('{{%category}}', [
            'id' => $this->bigPrimaryKey()->unsigned(),
            'user_id' => $this->integer()->unsigned()->notNull(),
            'legacy_id' => $this->integer()->unsigned()->notNull(),
            'name' => $this->string(80)->notNull(),
            'type' => $this->string(20)->notNull(),
            'icon' => $this->string(32)->null(),
            'keywords_json' => $this->text()->null(),
            'archived' => $this->boolean()->notNull()->defaultValue(false),
            'extra_json' => $this->text()->null(),
        ], $opt);
        $this->addForeignKey('fk_category_user', '{{%category}}', 'user_id', '{{%user}}', 'id', 'CASCADE');
        $this->createIndex('ux_category_user_legacy', '{{%category}}', ['user_id','legacy_id'], true);
        $this->createIndex('ix_category_user_name', '{{%category}}', ['user_id','name']);

        $this->createTable('{{%finance_transaction}}', [
            'id' => $this->bigPrimaryKey()->unsigned(),
            'user_id' => $this->integer()->unsigned()->notNull(),
            'legacy_id' => $this->integer()->unsigned()->notNull(),
            'type' => $this->string(20)->notNull(),
            'category' => $this->string(80)->null(),
            'amount' => $this->bigInteger()->notNull()->defaultValue(0),
            'note' => $this->string(500)->null(),
            'transaction_date' => $this->date()->notNull(),
            'wallet_id' => $this->integer()->unsigned()->null(),
            'from_wallet_id' => $this->integer()->unsigned()->null(),
            'to_wallet_id' => $this->integer()->unsigned()->null(),
            'spending_kind' => $this->string(20)->null(),
            'bill_id' => $this->integer()->unsigned()->null(),
            'source' => $this->string(40)->null(),
            'attachment_json' => $this->text()->null(),
            'ocr_json' => $this->text()->null(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->null(),
            'extra_json' => $this->text()->null(),
        ], $opt);
        $this->addForeignKey('fk_tx_user', '{{%finance_transaction}}', 'user_id', '{{%user}}', 'id', 'CASCADE');
        $this->createIndex('ux_tx_user_legacy', '{{%finance_transaction}}', ['user_id','legacy_id'], true);
        $this->createIndex('ix_tx_user_date', '{{%finance_transaction}}', ['user_id','transaction_date']);
        $this->createIndex('ix_tx_user_type_date', '{{%finance_transaction}}', ['user_id','type','transaction_date']);
        $this->createIndex('ix_tx_user_wallet_date', '{{%finance_transaction}}', ['user_id','wallet_id','transaction_date']);
        $this->createIndex('ix_tx_user_category_date', '{{%finance_transaction}}', ['user_id','category','transaction_date']);

        $this->createTable('{{%chat_message}}', [
            'id' => $this->bigPrimaryKey()->unsigned(),
            'user_id' => $this->integer()->unsigned()->notNull(),
            'legacy_id' => $this->integer()->unsigned()->notNull(),
            'role' => $this->string(20)->notNull(),
            'message' => $this->text()->notNull(),
            'attachment_json' => $this->text()->null(),
            'created_at' => $this->dateTime()->notNull(),
            'extra_json' => $this->text()->null(),
        ], $opt);
        $this->addForeignKey('fk_chat_user', '{{%chat_message}}', 'user_id', '{{%user}}', 'id', 'CASCADE');
        $this->createIndex('ux_chat_user_legacy', '{{%chat_message}}', ['user_id','legacy_id'], true);
        $this->createIndex('ix_chat_user_created', '{{%chat_message}}', ['user_id','created_at']);

        $this->createTable('{{%monthly_budget}}', [
            'user_id' => $this->integer()->unsigned()->notNull(),
            'category_id' => $this->integer()->unsigned()->notNull(),
            'month' => $this->string(7)->notNull(),
            'limit_amount' => $this->bigInteger()->notNull()->defaultValue(0),
            'enabled' => $this->boolean()->notNull()->defaultValue(true),
            'extra_json' => $this->text()->null(),
            'PRIMARY KEY ([[user_id]], [[category_id]], [[month]])',
        ], $opt);
        $this->addForeignKey('fk_budget_user', '{{%monthly_budget}}', 'user_id', '{{%user}}', 'id', 'CASCADE');
        $this->createIndex('ix_budget_user_month', '{{%monthly_budget}}', ['user_id','month']);

        $this->createTable('{{%bill}}', [
            'id' => $this->bigPrimaryKey()->unsigned(),
            'user_id' => $this->integer()->unsigned()->notNull(),
            'legacy_id' => $this->integer()->unsigned()->notNull(),
            'name' => $this->string(120)->notNull(),
            'amount' => $this->bigInteger()->notNull()->defaultValue(0),
            'due_date' => $this->date()->null(),
            'due_day' => $this->tinyInteger()->unsigned()->null(),
            'schedule_type' => $this->string(20)->notNull()->defaultValue('once'),
            'category' => $this->string(80)->null(),
            'wallet_id' => $this->integer()->unsigned()->null(),
            'reminder_days' => $this->tinyInteger()->unsigned()->notNull()->defaultValue(3),
            'active' => $this->boolean()->notNull()->defaultValue(true),
            'payments_json' => $this->text()->null(),
            'created_at' => $this->dateTime()->null(),
            'extra_json' => $this->text()->null(),
        ], $opt);
        $this->addForeignKey('fk_bill_user', '{{%bill}}', 'user_id', '{{%user}}', 'id', 'CASCADE');
        $this->createIndex('ux_bill_user_legacy', '{{%bill}}', ['user_id','legacy_id'], true);
        $this->createIndex('ix_bill_user_due', '{{%bill}}', ['user_id','active','due_date']);

        $this->createTable('{{%recurring_transaction}}', [
            'id' => $this->bigPrimaryKey()->unsigned(),
            'user_id' => $this->integer()->unsigned()->notNull(),
            'legacy_id' => $this->integer()->unsigned()->notNull(),
            'name' => $this->string(120)->notNull(),
            'type' => $this->string(20)->notNull(),
            'amount' => $this->bigInteger()->notNull(),
            'category' => $this->string(80)->null(),
            'wallet_id' => $this->integer()->unsigned()->null(),
            'frequency' => $this->string(20)->notNull(),
            'interval_value' => $this->integer()->unsigned()->notNull()->defaultValue(1),
            'next_run' => $this->date()->notNull(),
            'active' => $this->boolean()->notNull()->defaultValue(true),
            'created_at' => $this->dateTime()->null(),
            'extra_json' => $this->text()->null(),
        ], $opt);
        $this->addForeignKey('fk_recurring_user', '{{%recurring_transaction}}', 'user_id', '{{%user}}', 'id', 'CASCADE');
        $this->createIndex('ux_recurring_user_legacy', '{{%recurring_transaction}}', ['user_id','legacy_id'], true);
        $this->createIndex('ix_recurring_next', '{{%recurring_transaction}}', ['user_id','active','next_run']);

        $this->createTable('{{%saving_goal}}', [
            'id' => $this->bigPrimaryKey()->unsigned(),
            'user_id' => $this->integer()->unsigned()->notNull(),
            'legacy_id' => $this->integer()->unsigned()->notNull(),
            'name' => $this->string(120)->notNull(),
            'target_amount' => $this->bigInteger()->notNull(),
            'current_amount' => $this->bigInteger()->notNull()->defaultValue(0),
            'deadline' => $this->date()->null(),
            'active' => $this->boolean()->notNull()->defaultValue(true),
            'created_at' => $this->dateTime()->null(),
            'extra_json' => $this->text()->null(),
        ], $opt);
        $this->addForeignKey('fk_goal_user', '{{%saving_goal}}', 'user_id', '{{%user}}', 'id', 'CASCADE');
        $this->createIndex('ux_goal_user_legacy', '{{%saving_goal}}', ['user_id','legacy_id'], true);

        $this->createTable('{{%audit_log}}', [
            'id' => $this->bigPrimaryKey()->unsigned(),
            'user_id' => $this->integer()->unsigned()->notNull(),
            'legacy_id' => $this->integer()->unsigned()->notNull(),
            'action' => $this->string(50)->notNull(),
            'entity_type' => $this->string(50)->null(),
            'entity_id' => $this->integer()->null(),
            'label' => $this->string(255)->null(),
            'before_json' => $this->text()->null(),
            'after_json' => $this->text()->null(),
            'undoable' => $this->boolean()->notNull()->defaultValue(false),
            'undone_at' => $this->dateTime()->null(),
            'created_at' => $this->dateTime()->notNull(),
            'extra_json' => $this->text()->null(),
        ], $opt);
        $this->addForeignKey('fk_audit_user', '{{%audit_log}}', 'user_id', '{{%user}}', 'id', 'CASCADE');
        $this->createIndex('ux_audit_user_legacy', '{{%audit_log}}', ['user_id','legacy_id'], true);
        $this->createIndex('ix_audit_user_created', '{{%audit_log}}', ['user_id','created_at']);

        $this->createTable('{{%offline_operation}}', [
            'user_id' => $this->integer()->unsigned()->notNull(),
            'operation_id' => $this->string(64)->notNull(),
            'applied_at' => $this->dateTime()->notNull(),
            'response_json' => $this->text()->null(),
            'PRIMARY KEY ([[user_id]], [[operation_id]])',
        ], $opt);
        $this->addForeignKey('fk_offline_user', '{{%offline_operation}}', 'user_id', '{{%user}}', 'id', 'CASCADE');

        $this->createTable('{{%subscription_plan}}', [
            'plan_key' => $this->string(40)->notNull(),
            'label' => $this->string(80)->notNull(),
            'amount' => $this->integer()->unsigned()->notNull(),
            'monthly_equivalent' => $this->integer()->unsigned()->notNull()->defaultValue(0),
            'months' => $this->integer()->unsigned()->notNull()->defaultValue(0),
            'permanent' => $this->boolean()->notNull()->defaultValue(false),
            'description' => $this->string(255)->null(),
            'extra_json' => $this->text()->null(),
            'PRIMARY KEY ([[plan_key]])',
        ], $opt);

        $this->createTable('{{%payment_bank}}', [
            'bank_key' => $this->string(80)->notNull(),
            'name' => $this->string(100)->notNull(),
            'account_number' => $this->string(100)->notNull(),
            'account_name' => $this->string(120)->notNull(),
            'enabled' => $this->boolean()->notNull()->defaultValue(true),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'extra_json' => $this->text()->null(),
            'PRIMARY KEY ([[bank_key]])',
        ], $opt);

        $this->createTable('{{%coupon}}', [
            'id' => $this->primaryKey()->unsigned(),
            'code' => $this->string(60)->notNull(),
            'discount_type' => $this->string(30)->notNull(),
            'discount_value' => $this->integer()->notNull(),
            'expires_at' => $this->date()->null(),
            'enabled' => $this->boolean()->notNull()->defaultValue(true),
            'created_at' => $this->dateTime()->null(),
            'updated_at' => $this->dateTime()->null(),
            'extra_json' => $this->text()->null(),
        ], $opt);
        $this->createIndex('ux_coupon_code', '{{%coupon}}', 'code', true);

        $this->createTable('{{%subscription_order}}', [
            'id' => $this->primaryKey()->unsigned(),
            'user_id' => $this->integer()->unsigned()->notNull(),
            'invoice_no' => $this->string(80)->notNull(),
            'status' => $this->string(40)->notNull(),
            'plan_key' => $this->string(40)->null(),
            'base_amount' => $this->integer()->unsigned()->notNull()->defaultValue(0),
            'discount_amount' => $this->integer()->unsigned()->notNull()->defaultValue(0),
            'final_amount' => $this->integer()->unsigned()->notNull()->defaultValue(0),
            'bank_key' => $this->string(80)->null(),
            'proof_json' => $this->text()->null(),
            'order_json' => $this->text()->notNull(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ], $opt);
        $this->addForeignKey('fk_order_user', '{{%subscription_order}}', 'user_id', '{{%user}}', 'id', 'CASCADE');
        $this->createIndex('ux_order_invoice', '{{%subscription_order}}', 'invoice_no', true);
        $this->createIndex('ix_order_user_status', '{{%subscription_order}}', ['user_id','status']);
        $this->createIndex('ix_order_status_created', '{{%subscription_order}}', ['status','created_at']);

        $this->createTable('{{%app_document}}', [
            'namespace' => $this->string(80)->notNull(),
            'document_key' => $this->string(120)->notNull(),
            'payload_json' => $this->mediumText()->notNull(),
            'revision' => $this->bigInteger()->unsigned()->notNull()->defaultValue(0),
            'updated_at' => $this->dateTime()->notNull(),
            'PRIMARY KEY ([[namespace]], [[document_key]])',
        ], $opt);

        $this->createTable('{{%migration_history}}', [
            'id' => $this->bigPrimaryKey()->unsigned(),
            'source_name' => $this->string(190)->notNull(),
            'source_sha256' => $this->string(64)->null(),
            'mode' => $this->string(30)->notNull(),
            'status' => $this->string(30)->notNull(),
            'summary_json' => $this->mediumText()->null(),
            'created_by_user_id' => $this->integer()->unsigned()->null(),
            'created_at' => $this->dateTime()->notNull(),
            'completed_at' => $this->dateTime()->null(),
        ], $opt);
        $this->createIndex('ix_migration_created', '{{%migration_history}}', 'created_at');
    }

    public function safeDown()
    {
        foreach (['migration_history','app_document','subscription_order','coupon','payment_bank','subscription_plan','offline_operation','audit_log','saving_goal','recurring_transaction','bill','monthly_budget','chat_message','finance_transaction','category','wallet','user_setting','finance_meta','user_device','user'] as $table) {
            $this->dropTable('{{%'.$table.'}}');
        }
    }
}
