<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 27.11.2020
 * Time: 13:31
 */

namespace Parser\Model\Helper;


use Laminas\Db\Adapter\AdapterInterface;
use Laminas\Db\ResultSet\ResultSetInterface;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Sql;
use Laminas\Db\TableGateway\TableGateway;

/**
 * Class EventResultReference
 * @package Parser\Model\Helper
 * Keep common event results descriptions, for example, if scraping - log scraping formula
 */

class EventResultReference extends TableGateway
{
    public function __construct(AdapterInterface $adapter)
    {
        $table = 'event_result_reference';
        if (!Helper::checkTableExistance($adapter, $table)) {

            $filePath = 'data/table_page_logger/reference_result.sql';
            Helper::installDb($adapter, $table, $filePath);
        }
        parent::__construct($table, $adapter);
    }

    public function getReference($text) {
        $rowSet = $this->select(['event_result_hash' => md5($text)]);
        if($data = $rowSet->current()){
            return $data['event_result_reference_id'];
        }
    }

    public function addReference($text){
        $id = $this->getReference($text);
        if(!$id) {
            $this->insert(['event_result' => $text, 'event_result_hash' => md5($text), 'created' => new Expression('NOW()'), 'updated' => new Expression('NOW()')]);
            return $this->getReference($text);
        }
        return $id;
    }

}