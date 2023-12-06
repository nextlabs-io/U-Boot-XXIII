<?php

use yii\db\Migration;

/**
 * Handles adding userauth to table `proxy`.
 */
class m190317_050159_add_userauth_column_to_proxy_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        $this->addColumn('proxy', 'user_name', $this->string(255)->null());
        $this->addColumn('proxy', 'user_pass', $this->string(255)->null());
        $this->addColumn('proxy', 'tor_auth', $this->string(255)->null());
        $this->addColumn('proxy', 'tor_auth_port', $this->integer()->null());
        $this->execute("UPDATE `proxy` SET `enabled` = 1, `tor_auth` = 'tor-password', `tor_auth_port` = 9051 WHERE `ip`='127.0.0.1'");
    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        $this->dropColumn('proxy', 'user_name');
        $this->dropColumn('proxy', 'user_pass');
        $this->dropColumn('proxy', 'tor_auth');
        $this->dropColumn('proxy', 'tor_auth_port');
    }
}
