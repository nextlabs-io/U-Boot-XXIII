<?php

use yii\db\Migration;

/**
 * Handles the creation of table `product`.
 */
class m190216_160643_create_product_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        // TODO split the table into several, basetable, datatable, stockpricetable
        $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci ENGINE=MyISAM';
        $table = 'product';
        $this->createTable($table, [
            'product_id' => $this->primaryKey(),
            'asin' => $this->string(10)->notNull(),
            'parent_asin' => $this->string(10)->null(),
            'price' => $this->float()->null(),
            'prime' => 'BIT NOT NULL DEFAULT 0',
            'sku' => $this->string(50)->null(),
            'locale' => $this->string(3)->null(),
            'sync_speed' => $this->smallInteger()->notNull()->defaultValue(1),
            'curl_code' => $this->smallInteger()->null(),
            'sync_flag' => 'BIT NOT NULL DEFAULT 0',
            'syncable' => 'TINYINT NOT NULL DEFAULT 0',
            'enabled' => 'BIT NOT NULL DEFAULT 1',
            'offerUrl' => $this->string(255)->null(),
            'merchantOfferUrl' => $this->string(255)->null(),
            'productUrl' => $this->string(255)->null(),
            'sync_log' => $this->string(512)->null(),
            'sync_message' => $this->string(255)->null(),
            'merchantId' => $this->string(20)->null(),
            'merchantName' => $this->string(255)->null(),
            'shippingPrice' => $this->string(50)->null(),
            'shipping' => $this->string(255)->null(),
            'delivery' => $this->text()->null(),
            'title' => $this->string(512)->null(),
            'stock' => $this->smallInteger()->null(),
            'StockString' => $this->string(255)->null(),
            'isAddon' => 'BIT NOT NULL DEFAULT 0',
            'category' => $this->string(512)->null(),
            'description' => $this->text()->null(),
            'short_description' => $this->text()->null(),
            'mpn' => $this->string(60)->null(),
            'ean' => $this->string(60)->null(),
            'upc' => $this->string(60)->null(),
            'brand' => $this->string(255)->null(),
            'made_by' => $this->string(255)->null(),
            'manufacturer' => $this->string(255)->null(),
            'model' => $this->string(255)->null(),
            'images' => $this->text()->null(),
            'toDelete' => 'BIT NOT NULL DEFAULT 0',
            'weight' => $this->float()->null(),
            'dimension' => $this->float()->null(),
            'variation_attributes' => $this->string(512)->null(),
            'dimension_data' => $this->string(255)->null(),
            'delivery_data' => $this->string(255)->null(),
            'modified' => 'timestamp default current_timestamp on update current_timestamp',
            'updated_date' => $this->timestamp()->notNull()
                ->defaultExpression('CURRENT_TIMESTAMP'),
            'next_update_date' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            'created' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP')->notNull(),
        ], $tableOptions);
        $this->createIndex('idx-asin-locale', $table, ['asin', 'locale'], 1);
        $this->createIndex('idx-sync-flag', $table, 'sync_flag');
        $this->createIndex('idx-sku', $table, 'sku');
        $this->createIndex('idx-enabled', $table, 'enabled');
        $this->createIndex('idx-modified', $table, 'modified');
        $this->createIndex('idx-updated-date', $table, 'updated_date');
        $this->createIndex('idx-next-update-date', $table, 'next_update_date');
    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        $table = 'product';
        $this->dropIndex('idx-asin-locale', $table);
        $this->dropIndex('idx-sync-flag', $table);
        $this->dropIndex('idx-sku', $table);
        $this->dropIndex('idx-enabled', $table);
        $this->dropIndex('idx-modified', $table);
        $this->dropIndex('idx-updated-date', $table);
        $this->dropIndex('idx-next-update-date', $table);
        $this->dropTable($table);
    }
}
