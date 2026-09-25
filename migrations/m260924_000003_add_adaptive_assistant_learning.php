<?php

use yii\db\Migration;

/**
 * V20 - pembelajaran adaptif per akun.
 *
 * Tabel dipisahkan dari rule Pembelajaran Super Admin agar koreksi/pola
 * transaksi seorang pengguna tidak pernah dipakai untuk pengguna lain.
 */
final class m260924_000003_add_adaptive_assistant_learning extends Migration
{
    public function safeUp()
    {
        $opt = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';

        if ($this->db->schema->getTableSchema('{{%assistant_training_example}}', true) === null) {
            $this->createTable('{{%assistant_training_example}}', [
                'id' => $this->bigPrimaryKey()->unsigned(),
                'user_id' => $this->integer()->unsigned()->notNull(),
                'message_hash' => $this->string(64)->notNull(),
                'source_message' => $this->text()->notNull(),
                'normalized_text' => $this->string(500)->notNull(),
                'tokens_json' => $this->text()->null(),
                'parsed_json' => $this->text()->null(),
                'confirmed_json' => $this->text()->notNull(),
                'correction_json' => $this->text()->null(),
                'was_corrected' => $this->boolean()->notNull()->defaultValue(false),
                'use_count' => $this->integer()->unsigned()->notNull()->defaultValue(0),
                'last_used_at' => $this->dateTime()->null(),
                'created_at' => $this->dateTime()->notNull(),
                'updated_at' => $this->dateTime()->notNull(),
            ], $opt);
            $this->addForeignKey('fk_ai_example_user', '{{%assistant_training_example}}', 'user_id', '{{%user}}', 'id', 'CASCADE');
            $this->createIndex('ix_ai_example_user_created', '{{%assistant_training_example}}', ['user_id','created_at']);
            $this->createIndex('ix_ai_example_user_hash', '{{%assistant_training_example}}', ['user_id','message_hash']);
        }

        if ($this->db->schema->getTableSchema('{{%assistant_token_stat}}', true) === null) {
            $this->createTable('{{%assistant_token_stat}}', [
                'user_id' => $this->integer()->unsigned()->notNull(),
                'token' => $this->string(80)->notNull(),
                'field_name' => $this->string(40)->notNull(),
                'field_value' => $this->string(190)->notNull(),
                'positive_count' => $this->integer()->unsigned()->notNull()->defaultValue(0),
                'last_seen_at' => $this->dateTime()->notNull(),
                'PRIMARY KEY ([[user_id]], [[token]], [[field_name]], [[field_value]])',
            ], $opt);
            $this->addForeignKey('fk_ai_token_user', '{{%assistant_token_stat}}', 'user_id', '{{%user}}', 'id', 'CASCADE');
            $this->createIndex('ix_ai_token_user_field', '{{%assistant_token_stat}}', ['user_id','field_name','field_value']);
        }

        $this->db->schema->refresh();
        return true;
    }

    public function safeDown()
    {
        // Data training adalah data pengguna. Rollback kode V20 cukup mematikan
        // ADAPTIVE_AI_ENABLED; migration sengaja tidak menghapus data training.
        return true;
    }
}
