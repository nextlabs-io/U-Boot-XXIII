<?php

use yii\db\Migration;

/**
 * Class m201111_145556_add_comparator_product_table
 */
class m201111_145556_add_comparator_product_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_bin ENGINE=INNODB';
        $table = 'comparator_product';

        $fields = [
            'comparator_product_id' => $this->primaryKey(),
            'created' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated' => $this->timestamp()->null(),
            'url' => $this->text()->null(),
            'title' => $this->text()->null(),
            'description' => $this->text()->null(),
            'specs' => $this->text()->null(),
            'image' => $this->text()->null(),
            'asin' => $this->string(10)->null(),
            'locale' => $this->string(3)->null(),
            'brand' => $this->string(255)->null(),
            'model' => $this->string()->null(),
            'upc' => $this->string()->null(),
            'status' => $this->integer()->null(),
            'amazon_check' => $this->integer()->null(),
            'keepa_check' => $this->integer()->null(),
            'cdiscount_check' => $this->integer()->null(),
            'amazon_product_id' => $this->integer()->null(),
            'content' => 'LONGBLOB NULL DEFAULT NULL',
            'technical' => $this->integer()->null(),
            'ean' => $this->string()->null(),
            'price' => $this->string()->null(),
            'shipping_price' => $this->string()->null(),
            'stock' => $this->integer()->null(),
            'log' => $this->string()->null(),
            'next_update_date' => $this->dateTime(),
            'stock_html' => $this->text(),
            'price_html' => $this->text(),
            'short_description' => $this->text(),
        ];
        $this->createTable($table, $fields, $tableOptions);

        $this->createIndex('product_id-idx', $table, 'amazon_product_id');
        $this->createIndex('status-idx', $table, 'status');
        $this->createIndex('updated-idx', $table, 'updated');
        $this->createIndex('next-update-date-idx', $table, 'next_update_date');

    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropTable('comparator_product');
    }

}
