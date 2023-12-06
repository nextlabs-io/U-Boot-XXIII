<?php

use yii\db\Migration;

/**
 * Class m191013_122816_add_offers_qty_reviews_qty_olumn_to_post_table
 */
class m191013_122816_add_offers_qty_reviews_qty_olumn_to_post_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn('product', 'offers_qty', $this->integer());
        $this->addColumn('product', 'reviews_qty', $this->integer());
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropColumn('product', 'offers_qty');
        $this->dropColumn('product', 'reviews_qty');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m191013_122816_add_offers_qty_reviews_qty_olumn_to_post_table cannot be reverted.\n";

        return false;
    }
    */
}
