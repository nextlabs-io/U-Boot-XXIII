<?php

use yii\db\Migration;

/**
 * Class m200731_054807_add_product_table_fields_bb
 */
class m200731_054807_add_product_table_fields_bb extends Migration
{
    /**
     * @inheritdoc
     */

    public $integerColumns = [
        'customerRatingCount', 'isMarketplace', 'primaryParentCategoryId', 'seller_id',
    ];
    public $stringColumns = [
        'ehf', 'customerRating', 'sku', 'priceWithoutEhf', 'priceWithEhf', 'brandName',
        'modelNumber', 'productImage', 'seller_name', 'seller_rating_reviewsCount', 'seller_rating_score'
    ];
    public $textColumns = [
        'shortDescription',
        'seoText', 'altLangSeoText', 'seller_description',
    ];

    public function safeUp()
    {
        $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_bin ENGINE=MyISAM';
        $table = 'product_best_buy';
        foreach ($this->stringColumns as $column)
            $this->addColumn($table, $column, $this->string(255)->defaultValue(null));
        foreach ($this->textColumns as $column)
            $this->addColumn($table, $column, $this->text()->defaultValue(null));
        foreach ($this->integerColumns as $column)
            $this->addColumn($table, $column, $this->string(255)->defaultValue(null));
        $this->createIndex('idx-bbp-technical', $table, 'technical');

    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $table = 'product_best_buy';
        $allColumns = array_merge($this->stringColumns , $this->textColumns , $this->integerColumns);
        foreach ($allColumns as $column)
            $this->dropColumn($table, $column);

    }

}
