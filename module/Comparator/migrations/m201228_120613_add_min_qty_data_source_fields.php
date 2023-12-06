<?php

use yii\db\Migration;

/**
 * Class m201210_120613_add_keepa_fields
 */
class m201228_120613_add_min_qty_data_source_fields extends Migration
{
    public $stringColumns = [
        'minimum_qty',
        'data_source',
    ];

    public $textColumns = [
    ];

    public $integerColumns = [
    ];

    public function safeUp()
    {
        $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_bin ENGINE=MyISAM';
        $table = 'comparator_product';
        $this->addColumn($table, 'minimum_qty', $this->string(255)->defaultValue(1));
        $this->addColumn($table, 'data_source', $this->string(255)->defaultValue(null));

        $query = 'UPDATE ' . $table . ' set minimum_qty=1';
        $this->execute($query);
        $query = 'UPDATE ' . $table . ' set shipping_price=0 WHERE shipping_price is null';
        $this->execute($query);

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
