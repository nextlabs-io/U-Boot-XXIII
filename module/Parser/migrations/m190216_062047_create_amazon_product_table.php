<?php

use yii\db\Migration;

/**
 * Handles the creation of table `amazon_product`.
 */
class m190216_062047_create_amazon_product_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci ENGINE=MyISAM';
        $this->createTable('amazon_product', [
            'amazon_product_id' => $this->primaryKey(),
            'asin' => $this->string(10)->notNull(),
            'locale' => $this->string(3)->notNull()->defaultValue('com'),
            'api_response' => 'BIT DEFAULT 0',
            'title' => $this->text(),
            'ean' => $this->string(255)->defaultValue(null),
            'upc' => $this->string(255)->defaultValue(null),
            'brand' => $this->string(255)->defaultValue(null),
            'manufacturer' => $this->string(255)->defaultValue(null),
            'model' => $this->text(),
            'mpn' => $this->string(255)->defaultValue(null),
            'short_description' => $this->text(),
            'data' => $this->binary(),
            'modified' => 'timestamp  default current_timestamp  on update current_timestamp',
            'created' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
        ], $tableOptions);
    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        $this->dropTable('amazon_product');
    }
}
