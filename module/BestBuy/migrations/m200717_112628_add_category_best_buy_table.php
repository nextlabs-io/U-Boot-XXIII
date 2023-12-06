<?php

use yii\db\Migration;

/**
 * Class m200717_112628_add_category_best_buy_table
 */
class m200717_112628_add_category_best_buy_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
// --fields=url:string:null,bb_category:string,created:timestamp,updated:timestamp,title:string:null,page:integer:null,last_page:integer:null,status:integer:null

        $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE=MyISAM';
        $table = 'category_best_buy';

        $fields = [
            'category_best_buy_id' => $this->primaryKey(),
            'created' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated' => $this->timestamp()->null(),
            'bb_category' => $this->string()->null(),
            'url' => $this->string()->null(),
            'title' => $this->string()->null(),
            'page' => $this->integer()->null(),
            'last_page' => $this->integer()->null(),
            'status' => $this->integer()->null(),
        ];

        $this->createTable($table, $fields, $tableOptions);
        $this->createIndex('idx-bbp-category', $table, 'bb_category', 1);
        $this->createIndex('idx-bbp-status', $table, 'status');

    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropTable('category_best_buy');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m200717_112628_add_category_best_buy_table cannot be reverted.\n";

        return false;
    }
    */
}
