<?php

use yii\db\Migration;

/**
 * Class m201130_062242_change_cdiscount_product_table
 */
class m201130_062242_change_cdiscount_product_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $table = 'cdiscount_product';
        $this->execute("ALTER TABLE `" . $table . "` ENGINE = INNODB");
        $this->addColumn($table, 'next_update_date', $this->dateTime());
        $this->addColumn($table, 'stock_html', $this->text());
        $this->addColumn($table, 'price_html', $this->text());
        $this->addColumn($table, 'short_description', $this->text());
        $this->createIndex('status-idx', $table, 'status');
        $this->createIndex('updated-idx', $table, 'updated');
        $this->createIndex('next-update-date-idx', $table, 'next_update_date');
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $table = 'cdiscount_product';
        $this->execute("ALTER TABLE `cdiscount_product` ENGINE = MYISAM");
        $this->dropColumn($table, 'next_update_date');
        $this->dropColumn($table, 'stock_html');
        $this->dropColumn($table, 'price_html');
        $this->dropColumn($table, 'short_description');
        $this->dropIndex('status-idx', $table);
        $this->dropIndex('updated-idx', $table);
        $this->dropIndex('next-update-date-idx', $table);
        return true;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m201130_062242_change_cdiscount_product_table cannot be reverted.\n";

        return false;
    }
    */
}
