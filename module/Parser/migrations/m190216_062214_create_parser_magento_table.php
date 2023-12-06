<?php

use yii\db\Migration;

/**
 * Handles the creation of table `parser_magento`.
 */
class m190216_062214_create_parser_magento_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE=MyISAM';
        $table = 'parser_magento';
        $this->createTable($table, [
            'parser_magento_id' => $this->primaryKey(),
            'title' => $this->string(50)->notNull(),
            'enable' => 'BIT NOT NULL DEFAULT 1',
            'magento_trigger_path' => $this->string(255)->notNull(),
            'magento_trigger_key' => $this->string(255)->null(),
            'delete_trigger' => 'BIT NOT NULL DEFAULT 1',
            'create_trigger' => 'BIT NOT NULL DEFAULT 1',
            'send_images' => 'BIT NOT NULL DEFAULT 0',
            'check_description' => 'BIT NOT NULL DEFAULT 0',
        ], $tableOptions);
        $this->createIndex('idx-enable', $table, 'enable');
    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        $table = 'parser_magento';
        $this->dropIndex('idx-enable', $table);
        $this->dropTable($table);
    }
}
