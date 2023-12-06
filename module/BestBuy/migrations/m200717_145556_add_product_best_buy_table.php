<?php

use yii\db\Migration;

/**
 * Class m200717_145556_add_product_best_buy_table
 */
class m200717_145556_add_product_best_buy_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_bin ENGINE=MyISAM';
        $table = 'product_best_buy';

        $fields = [
            'product_best_buy_id' => $this->primaryKey(),
            'created' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated' => $this->timestamp()->null(),
            'bb_category' => $this->string(60)->null(),
            'bb_product' => $this->string(60)->null(),
            'url' => $this->text()->null(),
            'title' => $this->text()->null(),
            'description' => $this->text()->null(),
            'specs' => $this->text()->null(),
            'images' => $this->text()->null(),
            'asin' => $this->string(10)->null(),
            'locale' => $this->string(3)->null(),
            'model'=> $this->string()->null(),
            'upc' => $this->string()->null(),
            'status' => $this->integer()->null(),
            'content' => 'LONGBLOB NULL DEFAULT NULL',
            'amazon_content' => 'LONGBLOB NULL DEFAULT NULL',
            'technical' => $this->integer()->null(),
            'log' => $this->string()->null(),
        ];

        $this->createTable($table, $fields, $tableOptions);
        $this->createIndex('idx-bbp-product', $table, 'bb_category');
        $this->createIndex('idx-bbp-product-product', $table, 'bb_product');
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropTable('product_best_buy');
    }

}
