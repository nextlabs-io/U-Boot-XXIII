<?php

use yii\db\Migration;

/**
 * Handles dropping usage_log from table `proxy_connection`.
 */
class m200522_052023_drop_usage_log_column_from_proxy_connection_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        $this->dropColumn('proxy_connection', 'usage_log');
    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        $this->addColumn('proxy_connection', 'usage_log', $this->string(255));
    }
}
