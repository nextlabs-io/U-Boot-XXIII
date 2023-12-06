<?php

use yii\db\Migration;

/**
 * Class m200412_083029_add_fast_track_columnt_to_product_table
 */
class m200412_083029_add_fast_track_columnt_to_product_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $fields = ['fast_track'];
        foreach ($fields as $field) {
            $this->addColumn('product', $field, $this->text());
        }

    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {

        $fields = ['fast_track'];
        foreach ($fields as $field) {
            $this->dropColumn('product', $field);
        }

    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m200412_083029_add_fast_track_columnt_to_product_table cannot be reverted.\n";

        return false;
    }
    */
}
