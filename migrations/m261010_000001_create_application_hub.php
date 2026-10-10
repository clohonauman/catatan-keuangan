<?php

use yii\db\Migration;

final class m261010_000001_create_application_hub extends Migration
{
    public function safeUp()
    {
        $opt = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';

        $this->createTable('{{%app_catalog}}', [
            'id' => $this->primaryKey()->unsigned(),
            'slug' => $this->string(90)->notNull(),
            'name' => $this->string(120)->notNull(),
            'description' => $this->text()->null(),
            'icon' => $this->string(500)->notNull()->defaultValue('📱'),
            'app_url' => $this->string(1000)->notNull(),
            'callback_url' => $this->string(1000)->null(),
            'premium_required' => $this->boolean()->notNull()->defaultValue(false),
            'is_active' => $this->boolean()->notNull()->defaultValue(true),
            'sort_order' => $this->integer()->notNull()->defaultValue(100),
            'is_system' => $this->boolean()->notNull()->defaultValue(false),
            'internal_key' => $this->string(30)->null(),
            'client_id' => $this->string(80)->null(),
            'client_secret_hash' => $this->string(255)->null(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ], $opt);
        $this->createIndex('ux_app_catalog_slug', '{{%app_catalog}}', 'slug', true);
        $this->createIndex('ux_app_catalog_client_id', '{{%app_catalog}}', 'client_id', true);
        $this->createIndex('ix_app_catalog_active_order', '{{%app_catalog}}', ['is_active', 'sort_order']);

        $this->createTable('{{%app_sso_code}}', [
            'id' => $this->bigPrimaryKey()->unsigned(),
            'app_id' => $this->integer()->unsigned()->notNull(),
            'user_id' => $this->integer()->unsigned()->notNull(),
            'code_hash' => $this->char(64)->notNull(),
            'state_hash' => $this->char(64)->notNull(),
            'expires_at' => $this->dateTime()->notNull(),
            'used_at' => $this->dateTime()->null(),
            'created_at' => $this->dateTime()->notNull(),
        ], $opt);
        $this->addForeignKey('fk_app_sso_code_app', '{{%app_sso_code}}', 'app_id', '{{%app_catalog}}', 'id', 'CASCADE');
        $this->addForeignKey('fk_app_sso_code_user', '{{%app_sso_code}}', 'user_id', '{{%user}}', 'id', 'CASCADE');
        $this->createIndex('ux_app_sso_code_hash', '{{%app_sso_code}}', 'code_hash', true);
        $this->createIndex('ix_app_sso_code_expiry', '{{%app_sso_code}}', ['expires_at', 'used_at']);

        $this->createTable('{{%user_google_identity}}', [
            'id' => $this->primaryKey()->unsigned(),
            'user_id' => $this->integer()->unsigned()->notNull(),
            'google_sub' => $this->string(255)->notNull(),
            'email' => $this->string(190)->notNull(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ], $opt);
        $this->addForeignKey('fk_google_identity_user', '{{%user_google_identity}}', 'user_id', '{{%user}}', 'id', 'CASCADE');
        $this->createIndex('ux_google_identity_sub', '{{%user_google_identity}}', 'google_sub', true);
        $this->createIndex('ux_google_identity_user', '{{%user_google_identity}}', 'user_id', true);
        $this->createIndex('ix_google_identity_email', '{{%user_google_identity}}', 'email');
    }

    public function safeDown()
    {
        $this->dropTable('{{%user_google_identity}}');
        $this->dropTable('{{%app_sso_code}}');
        $this->dropTable('{{%app_catalog}}');
    }
}
