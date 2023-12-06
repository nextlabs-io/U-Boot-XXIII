<?php

use yii\db\Migration;

/**
 * Class m200731_105721_add_product_table_fields_bb_keepa
 */
class m200731_105721_add_product_table_fields_bb_keepa extends Migration
{
    public $stringColumns = [
        'keepa_asin',
        'keepa_brand',
        'keepa_product_group',
        'keepa_category',
        'keepa_manufacturer',
        'keepa_model',
        'keepa_local',
        'keepa_ean',
        'keepa_upc',
        'keepa_mpn',
        'keepa_part_number',
        'keepa_label',
        'keepa_type',
        'keepa_rootCategory',
        'keepa_publisher',
    ];

    public $textColumns = [
        'keepa_description',
        'keepa_title',
        'keepa_features',
        'keepa_image',
        'keepa_data'

    ];

    public $integerColumns = [
        'keepa_check'
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
            $this->addColumn($table, $column, $this->integer()->defaultValue(null));


    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $table = 'product_best_buy';
        $allColumns = array_merge($this->stringColumns, $this->textColumns, $this->integerColumns);
        foreach ($allColumns as $column)
            $this->dropColumn($table, $column);

    }
}
