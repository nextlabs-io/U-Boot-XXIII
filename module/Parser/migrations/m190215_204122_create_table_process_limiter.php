<?php

use yii\db\Migration;
use yii\db\Schema;

/**
 * Class m190215_204122_create_table_process_limiter
 */
class m190215_204122_create_table_process_limiter extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        // the table is not supposed to have lots of lines, and all fields are fixed, therefore only one index for path_id is set
        $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE=MyISAM';
        $this->createTable('process_limiter', [
            'process_limiter_id' => $this->primaryKey(),
            'path_id' => $this->integer()->comment('you can separate different processes by path_id'),
            'updated' => 'timestamp default current_timestamp on update current_timestamp',
            'created' => $this->timestamp()->notNull()
                ->defaultExpression('CURRENT_TIMESTAMP')->comment('timestamp created'),
            'expire' => $this->timestamp()->notNull()
                ->defaultExpression('CURRENT_TIMESTAMP')->comment('timestamp when it will become exprired'),
        ], $tableOptions);

        $this->createIndex('ids-path-id', 'process_limiter', 'path_id');
//        // creates index for column `author_id`
//        $this->createIndex(
//            'idx-post-author_id',
//            'post',
//            'author_id'
//        );
//
//        // add foreign key for table `user`
//        $this->addForeignKey(
//            'fk-post-author_id',
//            'post',
//            'author_id',
//            'user',
//            'id',
//            'CASCADE'
//        );

    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        // first drop keys
        //        // drops foreign key for table `user`
//        $this->dropForeignKey(
//            'fk-post-author_id',
//            'post'
//        );
//
        // drops index for column `path_id`
        $this->dropIndex(
            'ids-path-id',
            'process_limiter'
        );


        $this->dropTable('process_limiter');


    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m190215_204122_create_table_process_limiter cannot be reverted.\n";

        return false;
    }
    */
}
