<?php

use yii\db\Migration;

/**
 * Class m201006_103000_add
 */
class m201006_103000_add_amazon_category_page_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->createTable('amazon_category_page', [
            'amazon_category_page_id' => $this->primaryKey(),
            'amazon_category_id' => $this->integer(),
            'page' => $this->integer(),
        ]);
        $this->createIndex('idx-acp-amazon-category-id', 'amazon_category_page', 'amazon_category_id');
//        $this->createIndex('idx-acp-price', 'amazon_category_page', 'price');
        $this->execute('update amazon_category set `status` = ' .\Parser\Model\Amazon\Category::STATUS_NEVER_CHECKED. ' where `status` = ' . \Parser\Model\Amazon\Category::STATUS_IN_PROGRESS);

    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropTable('amazon_category_page');
    }
}
