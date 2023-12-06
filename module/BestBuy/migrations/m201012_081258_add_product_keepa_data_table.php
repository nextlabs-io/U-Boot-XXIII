<?php

use yii\db\Migration;

/**
 * Class m201012_081258_add_product_keepa_data_table
 */
class m201012_081258_add_product_keepa_data_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_bin ENGINE=MyISAM';
        $table = 'product_keepa_data';
        $fields = [
            'product_keepa_data_id' => $this->primaryKey(),
            'created' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated' => $this->timestamp()->null(),
            'asin' => $this->string(10)->null(),
            'locale' => $this->string(3)->null(),
            'brand' => $this->string(255)->null(),
            'product_group' => $this->string(255)->null(),
            'category' => $this->text()->null(),
            'manufacturer' => $this->string(255)->null(),
            'model' => $this->string(255)->null(),
            'ean' => $this->text()->null(),
            'upc' => $this->text()->null(),
            'mpn' => $this->string(255)->null(),
            'part_number' => $this->string(255)->null(),
            'label' => $this->string(255)->null(),
            'type' => $this->string(255)->null(),
            'rootCategory' => $this->string(255)->null(),
            'publisher' => $this->string(255)->null(),

            'description' => $this->text()->null(),
            'short_description' => $this->text()->null(),
            'long_description' => $this->text()->null(),
            'title' => $this->text()->null(),
            'features' => $this->text()->null(),
            'image' => $this->text()->null(),
            'data' => $this->text()->null(),
            'status' => $this->integer()->null(),
            'content' => 'LONGBLOB NULL DEFAULT NULL',
            'technical' => $this->integer()->null(),
            'log' => $this->string()->null(),
        ];

        $this->createTable($table, $fields, $tableOptions);
        $this->createIndex('idx-kpd-asin-locale', $table, ['asin', 'locale'], 1);
        $this->createIndex('idx-kpd-created', $table, ['created']);
        $this->createIndex('idx-kpd-updated', $table, ['updated']);
        $this->createIndex('idx-kpd-status', $table, 'status');
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $table = 'product_keepa_data';
        $this->dropTable($table);
    }

}
