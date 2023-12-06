<?php

use yii\db\Migration;

/**
 * Class m200915_042430_edit_broken_keepa_Elements
 */
class m200915_042430_edit_broken_keepa_Elements extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $table = 'product_keepa';
        $this->execute('UPDATE '. $table. ' SET technical = 5 WHERE content > \'\'');
        $this->execute('UPDATE '. $table. ' SET status = '.\BestBuy\Model\BestBuy\ProductKeepa::STATUS_NEVER_CHECKED.' WHERE status='.\BestBuy\Model\BestBuy\ProductKeepa::STATUS_CURRENTLY_IN_PROGRESS.' and updated < DATE_SUB(NOW(), INTERVAL 1 HOUR)');
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200915_042430_edit_broken_keepa_Elements cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m200915_042430_edit_broken_keepa_Elements cannot be reverted.\n";

        return false;
    }
    */
}
