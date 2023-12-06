<?php

use yii\db\Migration;

/**
 * Handles the creation of table `logger`.
 */
class m190216_062158_create_logger_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE=MyISAM';
        $table = 'logger';
        $this->createTable($table, [
            'logger_id' => $this->primaryKey(),
            'tag' => $this->string(30)->notNull(),
            'data'=> $this->string(255)->null(),
            'created' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP')
        ], $tableOptions);
        $this->createIndex('idx-tag', $table, 'tag');
    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        $table = 'logger';
        $this->dropIndex('idx-tag', $table);
        $this->dropTable($table);
    }
}
