<?php

use yii\db\Migration;

/**
 * Handles the creation of table `proxy_connection`.
 */
class m190216_062327_create_proxy_connection_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE=MyISAM';
        $table = 'proxy_connection';
        $this->createTable($table, [
            'proxy_connection_id' => $this->primaryKey(),
            'proxy_id' => $this->integer()->notNull(),
            'user_agent_id' => $this->integer()->notNull(),
            'created' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            'created_interval' => $this->integer()->unsigned()->notNull(),
            'closed' => 'BIT NOT NULL DEFAULT 0',
            'url' => $this->string(2048)->notNull(),
            'curl_code' => $this->smallInteger()->null(),
            'retry' => 'TINYINT DEFAULT NULL',
            'proxy_retry' => 'TINYINT DEFAULT NULL',
            'usage_log' => $this->string(64)->null(),
        ], $tableOptions);
        $this->createIndex('closed-created-interval-pr-id-curl-code', $table, [
            'closed', 'created_interval', 'proxy_id', 'curl_code'
        ]);
    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        $table = 'proxy_connection';
        $this->dropIndex('closed-created-interval-pr-id-curl-code', $table);
        $this->dropTable($table);
    }
}
