<?php

use yii\db\Migration;

/**
 * Handles the creation of table `product_logger`.
 */
class m190216_062307_create_product_logger_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE=MyISAM';
        $table = 'product_logger';
        $this->createTable($table, [
            'logger_id' => $this->primaryKey(),
            'product_id' => $this->integer()->null(),
            'tag' => $this->integer()->null(),
            'data'=> $this->string(255)->null(),
            'created' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP')
        ], $tableOptions);
        $this->createIndex('idx-product-tag-created', $table, ['product_id', 'tag', 'created']);
    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        $table = 'product_logger';
        $this->dropIndex('idx-product-tag-created', $table);
        $this->dropTable($table);
    }
}
