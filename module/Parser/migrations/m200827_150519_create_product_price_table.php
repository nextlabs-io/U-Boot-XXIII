<?php

use yii\db\Migration;

/**
 * Handles the creation of table `product_price`.
 */
class m200827_150519_create_product_price_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        $this->createTable('product_price', [
            'product_price_id' => $this->primaryKey(),
            'product_id' => $this->integer(),
            'created' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
            'price' => $this->float()->null(),
        ]);
        $this->createIndex('idx-ps-product-id', 'product_price', 'product_id');
        $this->createIndex('idx-ps-price', 'product_price', 'price');
        $this->createIndex('idx-ps-created', 'product_price', 'created');
    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        $this->dropTable('product_price');
    }
}
