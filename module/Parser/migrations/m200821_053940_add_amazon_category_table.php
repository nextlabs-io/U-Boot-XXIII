<?php

use yii\db\Migration;

/**
 * Class m200821_053940_add_amazon_category_table
 */
class m200821_053940_add_amazon_category_table extends Migration
{
    public function safeUp()
    {
// --fields=url:string:null,bb_category:string,created:timestamp,updated:timestamp,title:string:null,page:integer:null,last_page:integer:null,status:integer:null

        $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE=MyISAM';
        $table = 'amazon_category';

        $fields = [
            'amazon_category_id' => $this->primaryKey(),
            'created' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated' => $this->timestamp()->null(),
            'url' => $this->text()->null(),
            'next_page_url' => $this->text()->null(),
            'title' => $this->string()->null(),
            'page' => $this->integer()->null(),
            'product_fields' => $this->text()->null(),
            'last_page' => $this->integer()->null(),
            'status' => $this->integer()->null(),
        ];

        $this->createTable($table, $fields, $tableOptions);
        $this->createIndex('idx-amn-status', $table, 'status');

        $this->addColumn('product', 'amazon_category_id', $this->integer()->null());
        $this->createIndex('idx-product-amn-category_id', 'product', 'amazon_category_id');

    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropIndex('idx-product-amn-category_id', 'product');
        $this->dropColumn('product', 'amazon_category_id');
        $this->dropTable('amazon_category');
    }

}
