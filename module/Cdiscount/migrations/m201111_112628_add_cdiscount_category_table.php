<?php

use yii\db\Migration;

/**
 * Class m201111_112628_add_cdiscount_category_table
 */
class m201111_112628_add_cdiscount_category_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE=MyISAM';
        $table = 'cdiscount_category';

        /**
         * CREATE TABLE `amazon_category` (
        `` int(11) NOT NULL,
        `` timestamp NOT NULL DEFAULT current_timestamp(),
        `` timestamp NULL DEFAULT NULL,
        `` text DEFAULT NULL,
        `` text DEFAULT NULL,
        `` text DEFAULT NULL,
        `` text DEFAULT NULL,
        `` int(11) DEFAULT NULL,
        `` int(11) DEFAULT NULL,
        `` int(11) DEFAULT NULL,
        `` longblob DEFAULT NULL,
        `` longtext DEFAULT NULL,
        `` varchar(255) DEFAULT NULL,
        `` text DEFAULT NULL,
        `` int(11) DEFAULT NULL,
        `` varchar(60) DEFAULT NULL,
        `` varchar(255) DEFAULT NULL,
        `` varchar(255) DEFAULT NULL
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8;

        --
        -- Индексы сохранённых таблиц
        --

        --
        -- Индексы таблицы `amazon_category`
        --
        ALTER TABLE `amazon_category`
        ADD PRIMARY KEY (`amazon_category_id`),
        ADD KEY `idx-ac-category-index` (`category_index`),
        ADD KEY `ac-profile-idx` (`profile`),
        ADD KEY `idx-ac-marketplace_category` (`marketplace_category`),
        ADD KEY `idx-ac-web_hierarchy_location_codes` (`web_hierarchy_location_codes`);
         */

        $fields = [
            'cdiscount_category_id' => $this->primaryKey(),
            'created' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated' => $this->timestamp()->null(),
            'cd_category' => $this->string()->null(),
            'next_page_url' =>$this->string()->null(),
            'product_fields' =>$this->text()->null(),
            'content' => 'LONGBLOB NULL DEFAULT NULL',
            'json' =>'LONGTEXT NULL DEFAULT NULL',
            'profile' =>$this->string(60)->null(),
            'marketplace_category'=>$this->string()->null(),
            'web_hierarchy_location_codes'=>$this->string()->null(),
            'category_index' =>$this->string()->null(),
            'url' => $this->string()->null(),
            'title' => $this->string()->null(),
            'page' => $this->integer()->null(),
            'parent_id' =>$this->integer()->null(),
            'last_page' => $this->integer()->null(),
            'log' => $this->text()->null(),
            'status' => $this->integer()->null(),
        ];

        $this->createTable($table, $fields, $tableOptions);
        $this->createIndex('idx-cdp-category', $table, 'cd_category', 1);
        $this->createIndex('idx-cdp-status', $table, 'status');
        $this->createIndex('idx-cdp-profile', $table, 'profile');
        $this->createIndex('idx-cdp-marketplace_category', $table, 'marketplace_category');
        $this->createIndex('idx-cdp-web_hierarchy_location_codes', $table, 'web_hierarchy_location_codes');
        $this->createIndex('idx-cdp-category-index', $table, 'category_index');

    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropTable('cdiscount_category');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m200717_112628_add_category_best_buy_table cannot be reverted.\n";

        return false;
    }
    */
}
