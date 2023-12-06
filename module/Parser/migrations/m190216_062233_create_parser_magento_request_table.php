<?php

use yii\db\Migration;

/**
 * Handles the creation of table `parser_magento_request`.
 */
class m190216_062233_create_parser_magento_request_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci ENGINE=MyISAM';
        $table = 'parser_magento_request';
        $this->createTable($table, [
            'parser_magento_request_id' => $this->primaryKey(),
            'store_id' => $this->integer()->notNull(),
            'type' => 'TINYINT NOT NULL',
            'data' => $this->text(),
            'failed_state' => 'BIT NOT NULL DEFAULT 0',
            'process_log' => $this->text()->null(),
            'created' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP')->notNull(),
        ], $tableOptions);
        $this->createIndex('idx-store-type-created', $table, ['store_id', 'type', 'created']);
        $this->createIndex('idx-failed_state', $table,'failed_state');
    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        $table = 'parser_magento_request';
        $this->dropIndex('idx-store-type-created', $table);
        $this->dropIndex('idx-failed_state', $table);
        $this->dropTable($table);
    }
}
