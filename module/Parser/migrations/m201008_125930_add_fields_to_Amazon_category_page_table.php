<?php

use yii\db\Migration;

/**
 * Class m201008_125930_add_fields_to_Amazon_category_page_table
 */
class m201008_125930_add_fields_to_Amazon_category_page_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn('amazon_category_page', 'checked', $this->integer());
        $this->addColumn('amazon_category_page', 'found', $this->integer());
        $this->addColumn('amazon_category_page', 'url', $this->text());
        $this->createIndex('acp-checked-idx', 'amazon_category_page', 'checked');
        $this->addColumn('amazon_category', 'profile', $this->string(60));
        $this->createIndex('ac-profile-idx', 'amazon_category', 'profile');
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropIndex('acp-checked-idx', 'amazon_category_page');
        $this->dropColumn('amazon_category_page', 'checked');
        $this->dropColumn('amazon_category_page', 'found');
        $this->dropColumn('amazon_category_page', 'url');
    }


}
