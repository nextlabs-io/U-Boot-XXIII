<?php

use yii\db\Migration;

/**
 * Handles the creation of table `parser_magento_log`.
 */
class m190605_080450_create_parser_magento_log_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE=MyISAM';
        $table = 'parser_magento_log';

        $this->createTable($table, [
            'parser_magento_log_id' => $this->primaryKey(),
            'store_id' => $this->integer()->notNull(),
            'product_id' => $this->integer(),
            'action' => $this->integer(),
            'message' => $this->string(255),
            'error' => $this->string(255),
            'created' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            'description' => $this->text(),
        ], $tableOptions);
        $this->createIndex('idx-store_id', $table, 'store_id');
        $this->createIndex('idx-product_id', $table, 'product_id');
        $this->createIndex('idx-action', $table, 'action');
    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        $this->dropIndex('idx-store_id', 'parser_magento_log');
        $this->dropIndex('idx-product_id', 'parser_magento_log');
        $this->dropIndex('idx-action', 'parser_magento_log');

        $this->dropTable('parser_magento_log');
    }
}
