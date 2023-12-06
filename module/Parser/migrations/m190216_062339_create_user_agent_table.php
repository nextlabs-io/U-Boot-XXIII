<?php

use yii\db\Migration;

/**
 * Handles the creation of table `user_agent`.
 */
class m190216_062339_create_user_agent_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE=MyISAM';
        $table = 'user_agent';
        $this->createTable($table, [
            'user_agent_id' => $this->primaryKey(),
            'value' => $this->string(255)->notNull(),
            'enabled' => 'BIT NOT NULL DEFAULT 1',
            'active' => 'BIT NOT NULL DEFAULT 1',
            'usage_count' => $this->integer()->notNull()->defaultValue(0),
            'success_rate' => $this->smallInteger()->null(),
            'usage_log' => $this->string(255)->null(),
            'fail_count' => $this->smallInteger()->null(),
        ], $tableOptions);
        // TODO need to refactor indexes, or add another index
        $this->createIndex('idx-value', $table, 'value', 1);
        $this->createIndex('idx-active', $table, 'active');
        $this->createIndex('idx-enabled', $table, 'enabled');
        $this->createIndex('idx-usage-count', $table, 'usage_count');
        $this->createIndex('idx-success-rate', $table, 'success_rate');

        $this->insert($table, ['value' => 'Mozilla/5.0 (Windows NT 6.1; WOW64; Trident/7.0; rv:11.0) like Gecko']);
        $this->insert($table, ['value' => 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2228.0 Safari/537.36']);
        $this->insert($table, ['value' => 'Opera/9.80 (Windows NT 6.2; Win64; x64) Presto/2.12 Version/12.16']);
        $this->insert($table, ['value' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 10_3_1 like Mac OS X) AppleWebKit/603.1.30 (KHTML, like Gecko) Version/10.0 Mobile/14E304 Safari/602.1']);
        $this->insert($table, ['value' => 'Mozilla/5.0 (Linux; U; Android 4.4.2; en-us; SCH-I535 Build/KOT49H) AppleWebKit/534.30 (KHTML, like Gecko) Version/4.0 Mobile Safari/534.30']);
        $this->insert($table, ['value' => 'Mozilla/5.0 (Linux; Android 7.0; SM-G930V Build/NRD90M) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/59.0.3071.125 Mobile Safari/537.36']);

    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        $table = 'user_agent';
        $this->dropIndex('idx-value', $table);
        $this->dropIndex('idx-active', $table);
        $this->dropIndex('idx-enabled', $table);
        $this->dropIndex('idx-usage-count', $table);
        $this->dropIndex('idx-success-rate', $table);
        $this->dropTable($table);
    }
}
