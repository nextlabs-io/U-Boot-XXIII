<?php

use yii\db\Migration;

/**
 * Handles the creation of table `product_custom`.
 */
class m190216_062300_create_product_custom_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci ENGINE=MyISAM';
        $table = 'product_custom';
        $this->createTable($table, [
            'product_custom_id' => $this->primaryKey(),
            'product_id' => $this->integer()->notNull(),
            'custom_short_description_flag' => 'BIT NOT NULL DEFAULT 0',
            'custom_short_description' => $this->text()->null(),
            'custom_description_flag' => 'BIT NOT NULL DEFAULT 0',
            'custom_description' => $this->text()->null(),
            'custom_title_flag' => 'BIT NOT NULL DEFAULT 0',
            'custom_title' => $this->string(512)->null(),
            'custom_price_flag' => 'BIT NOT NULL DEFAULT 0',
            'custom_price' => $this->float()->null(),
            'custom_images_flag' => 'BIT NOT NULL DEFAULT 0',
            'custom_images' => $this->text()->null(),
            'custom_images_send' => 'BIT NOT NULL DEFAULT 0',
            'custom_category_flag' => 'BIT NOT NULL DEFAULT 0',
            'custom_category' => $this->string(255)->null(),
        ], $tableOptions);
        $this->createIndex('idx-product-id', $table, 'product_id',true);

    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        $table = 'product_custom';
        $this->dropIndex('idx-product-id', $table);
        $this->dropTable($table);
    }
}
