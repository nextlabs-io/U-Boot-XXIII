<?php

use yii\db\Migration;

/**
 * Handles adding custom to table `product`.
 */
class m190328_160316_add_custom_columns_to_product_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        //MarketplaceCategory , MarketplaceCategoryName, Web Hierarchy Location Codes, Web Hierarchy Location Name
        $this->addColumn('product', 'marketplace_category', $this->string(255));
        $this->addColumn('product', 'marketplace_category_name', $this->string(255));
        $this->addColumn('product', 'web_hierarchy_location_codes', $this->string(255));
        $this->addColumn('product', 'web_hierarchy_location_name', $this->string(255));
    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        $this->dropColumn('product', 'marketplace_category');
        $this->dropColumn('product', 'marketplace_category_name');
        $this->dropColumn('product', 'web_hierarchy_location_codes');
        $this->dropColumn('product', 'web_hierarchy_location_name');
    }
}
