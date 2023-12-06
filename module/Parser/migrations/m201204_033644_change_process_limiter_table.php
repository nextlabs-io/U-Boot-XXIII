<?php

use yii\db\Migration;

/**
 * Class m201204_033644_change_process_limiter_table
 */
class m201204_033644_change_process_limiter_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $table = 'process_limiter';
        $this->alterColumn($table, 'path_id', 'varchar(60)');
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $table = 'process_limiter';
        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m201204_033644_change_process_limiter_table cannot be reverted.\n";

        return false;
    }
    */
}
