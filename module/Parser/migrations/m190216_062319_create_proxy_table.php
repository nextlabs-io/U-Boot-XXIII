<?php

use yii\db\Migration;

/**
 * Handles the creation of table `proxy`.
 */
class m190216_062319_create_proxy_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE=MyISAM';
        $table = 'proxy';
        $this->createTable($table, [
            'proxy_id' => $this->primaryKey(),
            'ip' => $this->string(255)->notNull(),
            'port' => $this->smallInteger()->notNull(),
            'enabled' => 'BIT NOT NULL DEFAULT 1',
            'usage_count' => $this->integer()->notNull()->defaultValue(0),
            'last_used' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            'max_usage_limit' => $this->smallInteger()->notNull()->defaultValue(0),
        ], $tableOptions);

        $this->createIndex('idx-ip-enabled', $table, ['ip', 'enabled']);
        $this->createIndex('idx-ip', $table, 'ip');
        $this->createIndex('idx-max-usage-limit', $table, 'max_usage_limit');
    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        $table = 'proxy';
        $this->dropIndex('idx-ip-enabled', $table);
        $this->dropIndex('idx-ip', $table);
        $this->dropIndex('idx-max-usage-limit', $table);
        $this->dropTable($table);
    }
}
