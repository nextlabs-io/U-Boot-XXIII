<?php

use yii\db\Migration;

/**
 * Handles the creation of table `avito`.
 */
class m190216_055025_create_avito_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci ENGINE=MyISAM';
        $this->createTable('avito', [
            'avito_id' => $this->primaryKey(),
            'link_hash' => $this->string(130)->notNull(),
            'item_id' => $this->string(30)->notNull(),
            'link' => $this->string(255)->notNull(),
            'title' => $this->string(255)->notNull(),
            'price' => $this->string(100)->notNull(),
            'created' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
        ], $tableOptions);

        $this->createIndex('idx-link-hash', 'avito', 'link_hash');
    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        $this->dropIndex('idx-link-hash', 'avito');
        $this->dropTable('avito');
    }
}
