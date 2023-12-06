<?php

use yii\db\Migration;

/**
 * Class m200819_174540_add_category_to_best_buy_product_table
 */
class m200819_174540_add_category_to_best_buy_product_table extends Migration
{
    public function safeUp()
    {
        $table = 'product_best_buy';
        $this->addColumn($table, 'category_tree', $this->text()->null());
        $this->addColumn($table, 'category_tree_seo', $this->text()->null());
        $this->addColumn($table, 'category_tree_id', $this->text()->null());
        $this->execute('UPDATE '. $table. ' SET technical = 5 WHERE content > \'\'');

    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $table = 'product_best_buy';
        $this->dropColumn($table, 'category_tree');
        $this->dropColumn($table, 'category_tree_seo');
        $this->dropColumn($table, 'category_tree_id');
    }


}
