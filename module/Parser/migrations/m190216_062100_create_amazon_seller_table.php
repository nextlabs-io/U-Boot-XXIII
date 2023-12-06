<?php

use yii\db\Migration;

/**
 * Handles the creation of table `amazon_seller`.
 */
class m190216_062100_create_amazon_seller_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE=MyISAM';
        $this->createTable('amazon_seller', [
            'amazon_seller_id' => $this->primaryKey(),
            'asin' => $this->string(10)->null(),
            'seller' => $this->string(15)->null(),
            'locale' => $this->string(3)->null(),
        ], $tableOptions);
        $this->createIndex('idx-locale', 'amazon_seller', 'locale');
    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        $this->dropIndex('idx-locale', 'amazon_seller');
        $this->dropTable('amazon_seller');
    }
}
