<?php
/**
 * Created by WebExperiment.
 * User: dom
 * Date: 25.07.18
 * Time: 12:05
 */

namespace Parser\Model\Amazon;


use Parser\Model\Helper\Config;
use Parser\Model\SimpleObject;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Where;

class BrandBlacklist extends SimpleObject
{
    public $data;
    private $db;
    private $config;

    public function __construct(Config $config)
    {
        $this->db = $config->getDb();
        $this->config = $config;
    }

    public static function checkMadeByTag($tag, $locale, $adapter)
    {
        $tag = trim($tag);
        if (! $tag) {
            return true;
        }
        $sql = new Sql($adapter);
        $select = $sql->select(['bl' => 'brand_blacklist']);
        $where = new Where();
        $where->equalTo('brand', $tag);
        $where->equalTo('locale', $locale);
        $select->where($where);
        $stmt = $sql->prepareStatementForSqlObject($select);
        $result = $stmt->execute();
        if ($result->current()) {
            return false;
        }
        return true;
    }

    public function loadData()
    {
        $sql = new Sql($this->db);
        $select = $sql->select(['bl' => 'brand_blacklist']);
        $stmt = $sql->prepareStatementForSqlObject($select);
        $result = $stmt->execute();
        $locales = $this->config->getLocales();
        foreach ($locales as $locale) {
            $list[$locale] = [];
        }
        while ($item = $result->current()) {
            $list[$item['locale']][] = $item['brand'];
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

        $query = "DELETE FROM `brand_blacklist`";
        $stmt->setSql($query);
        $stmt->execute();
        $list = [];
        if (is_array($data) && count($data)) {
            foreach ($data as $locale => $brands) {
                $brands = trim($brands);
                $brands = str_replace(["\r", "\n"], ";;", $brands);
                $brands = explode(";;", $brands);
                if (count($brands)) {
                    foreach ($brands as $item) {
                        if (trim($item)) {
                            $list[] = ["locale" => $locale, "brand" => $item];
                        }
                    }
                }
            }
        }
        if (count($list)) {
            foreach ($list as $item) {
                $this->insert($item['locale'], $item['brand']);
            }
        }

    }

    private function insert($locale, $brand)
    {
        $sql = new Sql($this->db);
        $insert = $sql->insert('brand_blacklist');
        $insert->columns(['locale', 'brand']);
        $insert->values([$locale, $brand]);
        $stmt = $sql->prepareStatementForSqlObject($insert);
        $stmt->execute();

    }
}