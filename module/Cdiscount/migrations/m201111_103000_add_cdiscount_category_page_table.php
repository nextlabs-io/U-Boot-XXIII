<?php

use yii\db\Migration;

/**
 * Class m201111_103000_add_cdiscount_category_page_table
 */
class m201111_103000_add_cdiscount_category_page_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $table = 'cdiscount_category_page';
        $this->createTable($table, [
            'cdiscount_category_page_id' => $this->primaryKey(),
            'cdiscount_category_id' => $this->integer(),
            'checked' => $this->integer(),
            'url' => $this->text(),
            'found' => $this->integer(),
            'page' => $this->integer(),
        ]);
        $this->createIndex('idx-cdiscount-checked-idx', $table, 'checked');
        $this->createIndex('idx-acp-cdiscount-category-id', $table, 'cdiscount_category_id');
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropTable('cdiscount_category_page');
    }
}
