<?php

use yii\db\Migration;

/**
 * Handles adding modified to table `proxy_connection`.
 */
class m200811_084321_add_modified_column_to_proxy_connection_table extends Migration
{
    public function up()
    {
        $this->addColumn('proxy_connection', 'modified', $this->timestamp()->null());
    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        $this->dropColumn('proxy_connection', 'modified');


    }

}
