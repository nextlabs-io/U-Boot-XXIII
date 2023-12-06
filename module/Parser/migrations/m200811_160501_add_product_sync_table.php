<?php

use yii\db\Migration;

/**
 * Class m200811_160501_add_product_sync_table
 */
class m200811_160501_add_product_sync_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE=MyISAM';
        $table = 'product_sync';
        $this->createTable($table, [
            'product_sync_id' => $this->primaryKey(),
            'product_id' => $this->integer()->notNull(),
            'process_id' => $this->integer()->notNull(),
            'created' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP')
        ], $tableOptions);
        $this->createIndex('idx-product-id', $table, 'product_id', 1);
        $this->createIndex('idx-process-id', $table, 'process_id');
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropTable('product_sync');
    }


}
