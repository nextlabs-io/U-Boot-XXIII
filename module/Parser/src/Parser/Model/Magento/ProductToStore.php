<?php
/**
 * Copyright WebExperiment.info
 * Created by ernazar.
 * Date: 05.09.2018
 * Time: 19:21
 */

namespace Parser\Model\Magento;

use Parser\Model\SimpleObject;
use Laminas\Db\Adapter\AdapterInterface;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Where;

class ProductToStore extends SimpleObject
{
    /**
     * @param AdapterInterface $db
     * @param array or instance of Where    $whereProducts
     * @param array            $magentoList
     * @param bool             $removeFlag
     */
    public static function associateProducts(
        AdapterInterface $db,
        $whereProducts,
        $magentoList = [],
        $removeFlag = true
    ): void
    {
        // first delete old records
        if ($removeFlag) {
            self::removeAssociation($db, $whereProducts);
        }
        // now add new associations
        $sql = new Sql($db);
        if (count($magentoList)) {
            foreach ($magentoList as $magentoId) {
                $productsQuery = $sql->select('product')
                    ->columns(['product_id' => 'product_id', 'parser_magento_id' => new Expression('?', $magentoId)])
                    ->where($whereProducts);
                $insert = $sql->insert('parser_magento_product');
                $insert->columns(['product_id', 'parser_magento_id']);
                $insert->select($productsQuery);
                $stmt = $sql->prepareStatementForSqlObject($insert);
                $stmt->execute();
            }
        }
    }

    public static function removeAssociation($db, $whereProducts): void
    {
        $sql = new Sql($db);
        $productsQuery = $sql->select()
            ->from('product')
            ->columns(['product_id'])
            ->where($whereProducts);
        $where = new Where();
        $where->expression('product_id IN ?', $productsQuery);
        $del = $sql->delete('parser_magento_product')
            ->where($where);
        $stmt = $sql->prepareStatementForSqlObject($del);

        $stmt->execute();

    }

    public static function removeAssociationByStoreId($db, $storeId): void
    {
        $sql = new Sql($db);
        $del = $sql->delete('parser_magento_product')
            ->where(['parser_magento_id' => $storeId]);
        $stmt = $sql->prepareStatementForSqlObject($del);
        $stmt->execute();
    }

    public static function getStoreIdsByProduct($db, $productId): array
    {
        $sql = new Sql($db);
        $select = $sql->select('parser_magento_product')->columns(['parser_magento_id'])->where(['product_id' => $productId]);
        $stmt = $sql->prepareStatementForSqlObject($select);
        $result = $stmt->execute();
        $list = [];
        while ($result->current()) {
            $row = $result->current();
            $list[] = $row['parser_magento_id'];
            $result->next();
        }
        return $list;
    }

    /**
     * @param       $db
     * @param       $where - Where object for product table
     * @param array $storeOptions - conditions to which store should apply
     * @return array
     */
    public static function getStoreIdsByProductList($db, $where, $storeOptions = []): array
    {
        $sql = new Sql($db);
        $productsQuery = $sql->select()
            ->from('product')
            ->columns(['product_id'])
            ->where($where);
        $where = new Where();
        $where->expression('pmp.product_id IN ?', $productsQuery);
        $select = $sql->select(['pmp' => 'parser_magento_product'])
            ->columns(['product_id', 'parser_magento_id'])
            ->join(['p' => 'product'], 'p.product_id=pmp.product_id', ['asin', 'locale']);
        if ($storeOptions) {
            foreach ($storeOptions as $key => $option) {
                $where->equalTo('store.' . $key, $option);
            }
            $select->join(['store' => 'parser_magento'], 'store.parser_magento_id=pmp.parser_magento_id');
        }

        $select->where($where);

        $stmt = $sql->prepareStatementForSqlObject($select);
        $result = $stmt->execute();
        $list = [];
        while ($row = $result->current()) {
            $id = $row['product_id'];
            $mId = $row['parser_magento_id'];
            $list[$mId][$id] = ['asin' => $row['asin'], 'locale' => $row['locale']];
            $result->next();
        }
        return $list;
    }
}