<?php

use yii\db\Migration;

/**
 * Handles the creation of table `event_log`.
 */
class m190216_062130_create_event_log_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE=MyISAM';
        $table = 'event_log';
        $this->createTable($table, [
            'event_log_id' => $this->primaryKey(),
            'created' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP')->notNull(),
            'event_type' => 'TINYINT NULL',
            'event_log' => $this->string(25)->null(),
        ], $tableOptions);
        $this->createIndex('idx-event-type',$table,'event_type');

    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        $table = 'event_log';
        $this->dropIndex('idx-event-type', $table);
        $this->dropTable($table);
    }
}
