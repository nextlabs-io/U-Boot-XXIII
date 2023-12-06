<?php

use yii\db\Migration;

/**
 * Handles the creation of table `product_camel`.
 */
class m200209_140640_create_product_camel_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        $this->createTable('product_camel', [
            'product_camel_id' => $this->primaryKey(),
            'asin' => $this->string(10)->notNull(),
            'locale' => $this->string(3)->notNull()->defaultValue('com'),
            'ean' => $this->string(),
            'upc' => $this->string(),
            'status' => $this->integer(),
            'curl_code' => $this->integer(),
            'data' => $this->binary(),
            'updated' => 'timestamp  default current_timestamp  on update current_timestamp',
            'created' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
        ]);
        $this->createIndex('idx-locale-asin', 'product_camel', ['locale', 'asin']);
        $this->createIndex('idx-curl_code', 'product_camel', 'curl_code');
        $this->createIndex('idx-status', 'product_camel', 'status');
    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        $this->dropTable('product_camel');
    }
}
