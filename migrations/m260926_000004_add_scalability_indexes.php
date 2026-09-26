<?php

use yii\db\Migration;

class m260926_000004_add_scalability_indexes extends Migration
{
    public function safeUp()
    {
        $table='{{%finance_transaction}}';
        $this->createIndex('ix_tx_user_date_legacy',$table,['user_id','transaction_date','legacy_id']);
        $this->createIndex('ix_tx_user_amount_date',$table,['user_id','amount','transaction_date']);
        $this->createIndex('ix_tx_user_from_wallet_date',$table,['user_id','from_wallet_id','transaction_date']);
        $this->createIndex('ix_tx_user_to_wallet_date',$table,['user_id','to_wallet_id','transaction_date']);
    }

    public function safeDown()
    {
        $table='{{%finance_transaction}}';
        foreach(['ix_tx_user_to_wallet_date','ix_tx_user_from_wallet_date','ix_tx_user_amount_date','ix_tx_user_date_legacy'] as $name){
            $this->dropIndex($name,$table);
        }
    }
}
