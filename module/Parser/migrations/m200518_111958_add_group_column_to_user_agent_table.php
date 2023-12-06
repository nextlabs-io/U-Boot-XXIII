<?php

use yii\db\Migration;

/**
 * Handles adding group to table `user_agent`.
 */
class m200518_111958_add_group_column_to_user_agent_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        $this->addColumn('user_agent', 'group',  $this->string(20)->defaultValue(null));
    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        $this->dropColumn('user_agent', 'group');
    }
}
