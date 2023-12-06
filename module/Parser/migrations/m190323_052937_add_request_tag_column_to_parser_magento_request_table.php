<?php

use yii\db\Migration;

/**
 * Handles adding request_tag to table `parser_magento_request`.
 */
class m190323_052937_add_request_tag_column_to_parser_magento_request_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        $this->addColumn('parser_magento_request', 'request_tag', $this->string(64)->null());
        $this->createIndex('idx-request-tag', 'parser_magento_request', 'request_tag');
    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        $this->dropIndex('idx-request-tag', 'parser_magento_request');
        $this->dropColumn('parser_magento_request', 'request_tag');
    }
}
