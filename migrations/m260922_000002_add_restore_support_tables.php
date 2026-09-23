<?php

use yii\db\Migration;

/**
 * Compatibility migration untuk instalasi yang telah menjalankan
 * m260922_000001_create_mysql_schema versi awal, ketika app_document dan
 * migration_history belum ada di migration tersebut.
 *
 * Migration ini sengaja idempotent sehingga juga aman pada instalasi baru
 * yang sudah memiliki kedua tabel dari migration v1 terbaru.
 */
final class m260922_000002_add_restore_support_tables extends Migration
{
    public function safeUp()
    {
        $opt = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';

        if ($this->db->schema->getTableSchema('{{%app_document}}', true) === null) {
            $this->createTable('{{%app_document}}', [
                'namespace' => $this->string(80)->notNull(),
                'document_key' => $this->string(120)->notNull(),
                'payload_json' => $this->mediumText()->notNull(),
                'revision' => $this->bigInteger()->unsigned()->notNull()->defaultValue(0),
                'updated_at' => $this->dateTime()->notNull(),
                'PRIMARY KEY ([[namespace]], [[document_key]])',
            ], $opt);
        }

        if ($this->db->schema->getTableSchema('{{%migration_history}}', true) === null) {
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

        $this->db->schema->refresh();
        return true;
    }

    public function safeDown()
    {
        // Tidak menghapus tabel karena keduanya berisi arsip dan riwayat restore.
        // Rollback migration kompatibilitas tidak boleh menyebabkan kehilangan data.
        return true;
    }
}
