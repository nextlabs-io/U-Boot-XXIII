<?php

use yii\db\Migration;

/**
 * Class m201013_142019_add_2fields_to_amazon_category_table
 */
class m201013_142019_add_2fields_to_amazon_category_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        //marketplace_category
        //web_hierarchy_location_codes
        $this->addColumn('amazon_category', 'marketplace_category', $this->string()->null());
        $this->addColumn('amazon_category', 'web_hierarchy_location_codes', $this->string()->null());
        $this->createIndex('idx-ac-marketplace_category', 'amazon_category', 'marketplace_category');
        $this->createIndex('idx-ac-web_hierarchy_location_codes', 'amazon_category', 'web_hierarchy_location_codes');

    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropColumn('amazon_category', 'marketplace_category');
        $this->dropColumn('amazon_category', 'web_hierarchy_location_codes');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m201013_142019_add_2fields_to_amazon_category_table cannot be reverted.\n";

        return false;
    }
    */
}
