<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 30.11.2020
 * Time: 13:51
 */

namespace Cdiscount\Model\Cdiscount\Product;


use Cdiscount\Model\Cdiscount\Product;
use Parser\Model\DefaultTablePage;
use Parser\Model\Helper\Config;
use Parser\Model\Helper\EntitySync;
use Parser\Model\Helper\ProcessLimiter;
use Parser\Model\SimpleObject;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Sql;

class CdiscountSync extends EntitySync
{

    public function sync(ProcessLimiter $limiter)
    {

        /**
         * @var Product $this ->entity
         */
        $perRun = (int)$this->entity->getConfig('processLimiter', 'productsQtyPerRun');
        $processedProducts = 0;
        $productKey = $this->entity->getTableKey();
        $productTable = $this->entity->getTable();
        $updatedProducts = [];
        while ($processedProducts++ < $perRun && ($productData = $this->getCandidate())) {
            $this->entity->scrapeCDiscount($productData);
            $updatedProducts[] = $productData[$this->entity->getTableKey()];
        }
        $this->addMessage('processed :'. implode(',', $updatedProducts));
    }

    public function getCandidate(): array
    {
        $query = "UPDATE " . $this->entity->getTable() . " SET status=" . $this->entity::STATUS_CURRENTLY_IN_PROGRESS . " WHERE status!=" . $this->entity::STATUS_CURRENTLY_IN_PROGRESS . " AND (next_update_date < NOW() OR next_update_date IS NULL) and  LAST_INSERT_ID(`" . $this->entity->getTableKey() . "`) ORDER BY next_update_date ASC LIMIT 1";
        $adapter = $this->globalConfig->getDb();
        $sql = new Sql($adapter);
        $stmt = $sql->getAdapter()->getDriver()->createStatement();
        $stmt->setSql($query);
        $result = $stmt->execute();
        if ($productId = $result->getGeneratedValue()) {
            $rowSet = $this->entity->select([$this->entity->getTableKey() => $productId]);
            return (array)$rowSet->current();
        }
        return [];
    }

    public function load($where)
    {
        $res = $this->entity->select($where);
        if ($res->current()) {
            return (array)$res->current();
        }
        return [];
    }
}