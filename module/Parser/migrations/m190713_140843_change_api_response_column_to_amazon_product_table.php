<?php

use yii\db\Migration;

/**
 * Class m190713_140843_change_api_response_column_to_amazon_product_table
 */
class m190713_140843_change_api_response_column_to_amazon_product_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        //ALTER TABLE `brand_blacklist` CHANGE `locale` `locale` VARCHAR(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL;
        // ALTER TABLE `amazon_product` CHANGE `api_response` `api_response` TINYINT(1) NULL DEFAULT '0';
        $this->alterColumn('amazon_product', 'api_response', 'TINYINT NULL DEFAULT 0');
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->alterColumn('amazon_product', 'api_response', 'BIT NULL DEFAULT 0');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m190713_140843_change_api_response_column_to_amazon_product_table cannot be reverted.\n";

        return false;
    }
    */
}
