<?php

use yii\db\Migration;

/**
 * Class m200914_155615_add_product_keepa_table
 */
class m200914_155615_add_product_keepa_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_bin ENGINE=MyISAM';
        $table = 'product_keepa';
        $fields = [
            'product_keepa_id' => $this->primaryKey(),
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
        $this->createIndex('idx-kp-asin-locale', $table, ['asin', 'locale'], 1);
        $this->createIndex('idx-kp-created', $table, ['created']);
        $this->createIndex('idx-kp-updated', $table, ['updated']);
        $this->createIndex('idx-kp-status', $table, 'status');
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $table = 'product_keepa';
        $this->dropTable($table);

    }
}
