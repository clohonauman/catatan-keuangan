<?php
use yii\db\Migration;

class m260926_000004_add_scale_indexes extends Migration
{
    private function hasIndex(string $table,string $name): bool
    {
        $rows=$this->db->createCommand('SHOW INDEX FROM '.$this->db->quoteTableName($table))->queryAll();
        foreach($rows as $row)if((string)($row['Key_name']??'')===$name)return true;
        return false;
    }
    public function safeUp()
    {
        $table=$this->db->tablePrefix.'finance_transaction';
        if(!$this->hasIndex($table,'ix_tx_user_from_wallet_date'))$this->createIndex('ix_tx_user_from_wallet_date','{{%finance_transaction}}',['user_id','from_wallet_id','transaction_date']);
        if(!$this->hasIndex($table,'ix_tx_user_to_wallet_date'))$this->createIndex('ix_tx_user_to_wallet_date','{{%finance_transaction}}',['user_id','to_wallet_id','transaction_date']);
        if(!$this->hasIndex($table,'ix_tx_user_amount_date'))$this->createIndex('ix_tx_user_amount_date','{{%finance_transaction}}',['user_id','amount','transaction_date']);
    }
    public function safeDown()
    {
        $table=$this->db->tablePrefix.'finance_transaction';
        foreach(['ix_tx_user_from_wallet_date','ix_tx_user_to_wallet_date','ix_tx_user_amount_date'] as $idx)if($this->hasIndex($table,$idx))$this->dropIndex($idx,'{{%finance_transaction}}');
    }
}
