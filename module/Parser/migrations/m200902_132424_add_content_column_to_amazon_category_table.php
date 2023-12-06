<?php

use yii\db\Migration;

/**
 * Handles adding content to table `amazon_category`.
 */
class m200902_132424_add_content_column_to_amazon_category_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        $this->addColumn('amazon_category', 'content', 'LONGBLOB NULL DEFAULT NULL');
        $this->addColumn('amazon_category', 'json', 'LONGTEXT NULL DEFAULT NULL');
        $this->addColumn('amazon_category', 'category_index', $this->string(255)->null());
        $this->createIndex('idx-ac-category-index', 'amazon_category', 'category_index');

    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        $this->dropColumn('amazon_category', 'content');
        $this->dropColumn('amazon_category', 'json');
        $this->dropColumn('amazon_category', 'category_index');
    }
}
