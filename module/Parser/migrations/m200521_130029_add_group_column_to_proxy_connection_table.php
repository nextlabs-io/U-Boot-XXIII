<?php

use yii\db\Migration;

/**
 * Handles adding group to table `proxy_connection`.
 */
class m200521_130029_add_group_column_to_proxy_connection_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        $this->addColumn('proxy_connection', 'group', $this->string(20)->null());
        $this->addColumn('proxy_connection', 'tag', $this->string(20)->null());
        $this->createIndex('idx-pc-group', 'proxy_connection', 'group');
        $this->createIndex('idx-pc-tag', 'proxy_connection', 'tag');
    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        $this->dropIndex('idx-pc-group', 'proxy_connection');
        $this->dropIndex('idx-pc-tag', 'proxy_connection');
        $this->dropColumn('proxy_connection', 'group');
        $this->dropColumn('proxy_connection', 'tag');

    }
}
