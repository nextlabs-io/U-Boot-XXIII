<?php

use yii\db\Migration;

/**
 * Handles the creation of table `product_stock`.
 */
class m200827_150507_create_product_stock_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {

        $this->createTable('product_stock', [
            'product_stock_id' => $this->primaryKey(),
            'product_id' => $this->integer(),
            'created' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
            'stock' => $this->integer()->null(),
        ]);
        $this->createIndex('idx-ps-product-id', 'product_stock', 'product_id');
        $this->createIndex('idx-ps-stock', 'product_stock', 'stock');
        $this->createIndex('idx-ps-created', 'product_stock', 'created');

    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        $this->dropTable('product_stock');
    }
}
