<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 07.10.2020
 * Time: 12:10
 */

namespace Parser\Model\Amazon;


use Parser\Model\Helper\TablePageLogger;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Where;
use Laminas\Db\TableGateway\TableGateway;

class CategoryPage extends TableGateway implements CategoryPageInterface
{
    public $tableKey = 'amazon_category_page_id';
    public $categoryIdKey = 'amazon_category_id';
    public $eventLogger;
    public static $events  = [
        'scrape' => 1,
        'filterProducts' => 2,
    ];
    public function __construct($adapter, $table = 'amazon_category_page')
    {
        parent::__construct($table, $adapter);
    }

    public function getPagesQty($categoryId)
    {
        $sql = new Sql($this->getAdapter());
        $select = $sql->select($this->getTable())
            ->columns(['qty' => new Expression('COUNT(*)')])
            ->where([$this->categoryIdKey => $categoryId, 'checked' => 0]);
        $result = $this->selectWith($select);
        return $result->current()['qty'] ?? 0;
    }



    /**
     * @param array $cleanPages
     * @param $categoryId
     * @return int
     */
    public function addPages(array $cleanPages, $categoryId): int
    {
        /**
         * $cleanPages = [0 => ['page' => 0, 'selected' => 0, 'url' => , 'checked' => ]]
         */
        $affected = 0;
        foreach ($cleanPages as $page) {
            $row = $this->select(['page' => $page['page'], $this->categoryIdKey => $categoryId]);

            if(!$row->current()) {
                $affected += $this->insert([
                    $this->categoryIdKey => $categoryId,
                    'page' => $page['page'],
                    'url' => $page['url'],
                    'found' => $page['found'],
                    'checked' => $page['checked'] ? 1 : 0
                ]);
            }
        }
        return $affected;
    }

    public function loadPageCandidate($categoryId = null)
    {
        // get a page from amazon_category_page
        // there is a list of pages to process. this table is designed to allow a multithread access to the pages scraping
        $sql = new Sql($this->getAdapter());
        $select = $sql->select($this->getTable())
            ->limit(10);
        $where = new Where();
        $where->notEqualTo('checked', 1);
        if ($categoryId) {
            $where->equalTo($this->categoryIdKey, $categoryId);
        }
        $select->where($where);

        $select->order($this->tableKey . ' asc');
        $result = $this->selectWith($select);
        while ($row = $result->current()) {
            $result->next();
            $id = $row[$this->tableKey];
            $updated = $this->update(['checked' => true], [$this->tableKey => $id]);
            if ($updated) {
                return $row;
            }
        }
        return [];
    }

    public function addEvent($entityId, $eventType, $eventResult, $desc = null){
        if(!$this->eventLogger) {
            $this->eventLogger = new TablePageLogger($entityId, 'amazon_category_page',  $this->getAdapter());
        }
        $this->eventLogger->entity_id = $entityId;
        $this->eventLogger->addEvent($entityId, $eventType, $eventResult, $desc);
    }
}