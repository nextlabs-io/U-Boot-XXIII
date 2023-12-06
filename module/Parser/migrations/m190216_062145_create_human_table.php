<?php

use yii\db\Migration;

/**
 * Handles the creation of table `human`.
 */
class m190216_062145_create_human_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE=MyISAM';
        $table = 'human';
        $this->createTable($table, [
            'human_id' => $this->primaryKey(),
            'login' => $this->string(64)->notNull(),
            'password' => $this->string(64)->notNull(),
            'password_salt' => $this->string(64)->null(),
            'name' => $this->string(128)->null(),
            'data' => $this->text()->null(),
            'email' => $this->string(100)->null(),
        ], $tableOptions);
        $this->createIndex('idx-login', $table, 'login');
        $this->insert($table, ['human_id' => 1, 'login' => 'ernazar', 'password' => md5('admin'), 'email' => '', 'name' => 'ernazar']);
        $this->insert($table, ['human_id' => 2, 'login' => 'admin', 'password' => md5('admin'), 'email' => '', 'name' => 'Admin']);
    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        $table = 'human';
        $this->dropIndex('idx-login', $table);
        $this->dropTable($table);
    }
}
