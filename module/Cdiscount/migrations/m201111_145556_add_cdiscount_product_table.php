<?php

use yii\db\Migration;

/**
 * Class m201111_145556_add_cdiscount_product_table
 */
class m201111_145556_add_cdiscount_product_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_bin ENGINE=MyISAM';
        $table = 'cdiscount_product';

        $fields = [
            'cdiscount_product_id' => $this->primaryKey(),
            'cdiscount_category_id' => $this->integer()->null(),
            'created' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated' => $this->timestamp()->null(),
            'url' => $this->text()->null(),
            'title' => $this->text()->null(),
            'description' => $this->text()->null(),
            'specs' => $this->text()->null(),
            'images' => $this->text()->null(),
            'asin' => $this->string(10)->null(),
            'locale' => $this->string(3)->null(),
            'model' => $this->string()->null(),
            'upc' => $this->string()->null(),
            'status' => $this->integer()->null(),
            'cd_status' => $this->integer()->null(),
            'content' => 'LONGBLOB NULL DEFAULT NULL',
            'technical' => $this->integer()->null(),
            'ean' => 'BIGINT(15) NULL DEFAULT NULL',
            'price' => $this->float()->null(),
            'stock' => $this->integer()->null(),
            'log' => $this->string()->null(),
        ];
        $this->createTable($table, $fields, $tableOptions);

        $this->createIndex('idx-cdp-category', $table, 'cdiscount_category_id');
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropTable('cdiscount_product');
    }

}
