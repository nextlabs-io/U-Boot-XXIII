<?php

use yii\db\Migration;

/**
 * Handles adding group to table `proxy`.
 */
class m200518_111950_add_group_column_to_proxy_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        $this->addColumn('proxy', 'group', $this->string(20)->defaultValue(null));
    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        $this->dropColumn('proxy', 'group');
    }
}
