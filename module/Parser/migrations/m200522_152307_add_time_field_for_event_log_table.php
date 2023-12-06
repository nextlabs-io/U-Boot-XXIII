<?php

use yii\db\Migration;

/**
 * Class m200522_152307_add_time_field_for_event_log_table
 */
class m200522_152307_add_time_field_for_event_log_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn('event_log', 'time_spent', $this->integer());
        $this->createIndex('idx-el-time-spent', 'event_log', 'time_spent');

    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropIndex('idx-el-time-spent', 'event_log');
        $this->dropColumn('event_log', 'time_spent');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m200522_152307_add_time_field_for_event_log_table cannot be reverted.\n";

        return false;
    }
    */
}
