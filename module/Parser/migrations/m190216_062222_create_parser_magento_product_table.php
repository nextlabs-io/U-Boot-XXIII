<?php

use yii\db\Migration;

/**
 * Handles the creation of table `parser_magento_product`.
 */
class m190216_062222_create_parser_magento_product_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE=MyISAM';
        $table = 'parser_magento_product';
        $this->createTable($table, [
            'parser_magento_id' => $this->smallInteger()->notNull(),
            'product_id' => $this->integer()->notNull(),
        ], $tableOptions);
        $this->createIndex('idx-unq-prod-id-mag-id', $table, ['parser_magento_id', 'product_id']);
    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        $table = 'parser_magento_product';
        $this->dropIndex('idx-unq-prod-id-mag-id', $table);
        $this->dropTable($table);
    }
}
