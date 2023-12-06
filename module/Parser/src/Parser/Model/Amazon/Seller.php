<?php
/**
 * Copyright WebExperiment.info
 * Created by ernazar.
 * Date: 10.05.2018
 * Time: 19:38
 */

namespace Parser\Model\Amazon;


use Parser\Model\Helper\Config;
use Parser\Model\SimpleObject;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Where;

class Seller extends SimpleObject
{
    public $data;
    private $db;
    private $config;

    public function __construct(Config $config)
    {
        $this->db = $config->getDb();
        $this->config = $config;
    }

    public static function getBlockedSellerId($asin, $locale, $adapter)
    {
        $sql = new Sql($adapter);
        $select = $sql->select(['am' => 'amazon_seller']);
        $w = new Where();
        $w->equalTo('am.locale', $locale);
        $w->in('am.asin', [$asin, ""]);
        $w->or;
        $w->isNull('am.asin');
        $select->where($w);
        $stmt = $sql->prepareStatementForSqlObject($select);
        $result = $stmt->execute();
        $list = [];
        while($result->current()){
            $list[] = $result->current();
            $result->next();
        }
        $result = [];

        if (count($list)) {
            foreach ($list as $k => $seller) {
                if (! $seller['seller'] && $seller['asin'] == $asin) {
                    // all offers for that asin has to be skipped
                    $result['skipAll'] = 1;
                } else {
                    $result[] = $seller['seller'];
                }
            }
        }
        return $result;
    }

    public function loadData()
    {
        $sql = new Sql($this->db);
        $select = $sql->select('amazon_seller');
        $stmt = $sql->prepareStatementForSqlObject($select);
        $result = $stmt->execute();
        $locales = $this->config->getLocales();
        foreach ($locales as $locale) {
            $list[$locale] = [];
        }
        while ($item = $result->current()) {
            $locale = $item['locale'];
            $asin = $item['asin'];
            $seller = $item['seller'];
            $string = trim($asin . " " . $seller);
            if ($locale) {
                $list[$locale][] = $string;
            }
            $result->next();
        }
        $this->data = $list;
        return $this->data;
    }

    public function saveData($data)
    {
        $sql = new Sql($this->db);

        /* first delete old data */
        $stmt = $sql->getAdapter()->getDriver()->createStatement();

        $query = "DELETE FROM `amazon_seller`";
        $stmt->setSql($query);
        $stmt->execute();
        $list = [];
        if (is_array($data) && count($data)) {
            foreach ($data as $locale => $seller) {
                $seller = trim($seller);
                $seller = str_replace(["\r", "\n"], ";;", $seller);
                $seller = explode(";;", $seller);
                if (count($seller)) {
                    foreach ($seller as $item) {
                        $data = self::parserString($item);
                        if ($data) {
                            $list[] = ["locale" => $locale, "seller" => $data['seller'], 'asin' => $data['asin']];
                        }
                    }
                }
            }
        }
        if (count($list)) {
            foreach ($list as $item) {
                $this->insert($item['locale'], $item['seller'], $item['asin']);
            }
        }

    }

    /**
     * @param $string
     * @return array|bool - list of asin and seller
     */
    public static function parserString($string)
    {
        $string = trim($string);
        $length = strlen($string);
        if ($length > 10) {
            $items = explode(" ", $string);
            foreach ($items as $value) {
                if (trim($value)) {
                    $data[] = $value;
                }
            }
            if (count($items) > 1) {
                if (self::isAsin($items[0])) {
                    return ['asin' => $items[0], 'seller' => $items[1]];
                } elseif (self::isAsin($items[1])) {
                    return ['asin' => $items[1], 'seller' => $items[0]];
                }
            } else {
                return ['asin' => null, 'seller' => $string];
            }
        } elseif ($length == 10) {
            return ['asin' => $string, 'seller' => null];
        }
        return false;
    }

    public static function isAsin($string)
    {
        return strlen(trim($string)) == 10 ? true : false;
    }


    private function insert($locale, $seller, $asin)
    {
        $sql = new Sql($this->db);
        $insert = $sql->insert('amazon_seller');
        $insert->columns(['locale', 'seller', 'asin']);
        if (strlen($asin) > 10) {
            $asin = substr($asin, 0, 10);
        }
        if (strlen($seller) > 30) {
            $seller = substr($seller, 0, 30);
        }

        $insert->values([$locale, $seller, $asin]);
        $stmt = $sql->prepareStatementForSqlObject($insert);
        $stmt->execute();

    }


}