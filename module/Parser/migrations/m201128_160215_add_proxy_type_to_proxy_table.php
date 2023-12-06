<?php

use yii\db\Migration;

/**
 * Class m201128_160215_add_proxy_type_to_proxy_table
 */
class m201128_160215_add_proxy_type_to_proxy_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn('proxy', 'proxy_type', "ENUM('http','socks5','none') NOT NULL DEFAULT 'http'");
        $this->addColumn('proxy', 'proxy_character', $this->string(60)->null());
        $this->createIndex('proxy-type-idx', 'proxy', 'proxy_type');
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropColumn('proxy', 'proxy_type');
        $this->dropColumn('proxy', 'proxy_character');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m201128_160215_add_proxy_type_to_proxy_table cannot be reverted.\n";

        return false;
    }
    */
}
