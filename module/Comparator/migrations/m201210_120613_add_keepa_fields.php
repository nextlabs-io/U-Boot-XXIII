<?php

use yii\db\Migration;

/**
 * Class m201210_120613_add_keepa_fields
 */
class m201210_120613_add_keepa_fields extends Migration
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

    ];

    public function safeUp()
    {
        $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_bin ENGINE=MyISAM';
        $table = 'comparator_product';
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
        $table = 'comparator_product';
        $allColumns = array_merge($this->stringColumns, $this->textColumns, $this->integerColumns);
        foreach ($allColumns as $column)
            $this->dropColumn($table, $column);

    }
}
