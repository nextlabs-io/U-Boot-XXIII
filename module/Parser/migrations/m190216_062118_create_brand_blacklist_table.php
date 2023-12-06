<?php

use yii\db\Migration;

/**
 * Handles the creation of table `brand_blacklist`.
 */
class m190216_062118_create_brand_blacklist_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE=MyISAM';
        $table = 'brand_blacklist';
        $this->createTable($table, [
            'brand_blacklist_id' => $this->primaryKey(),
            'locale' => $this->string(4)->notNull(),
            'brand' => $this->string(255)->notNull(),
        ], $tableOptions);
        $this->createIndex('idx-locale-brand', $table, ['locale', 'brand']);
    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        $table = 'brand_blacklist';
        $this->dropIndex('idx-locale-brand', $table);
        $this->dropTable($table);
    }
}
