<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 27.11.2020
 * Time: 13:03
 */

namespace Parser\Model\Helper;


use Laminas\Db\Adapter\AdapterInterface;
use Laminas\Db\ResultSet\ResultSetInterface;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Sql;
use Laminas\Db\TableGateway\TableGateway;

// simple class to log common events per table page, with own clean routines etc.

class TablePageLogger extends TableGateway
{

    public $entity_id;
    /**
     * @var EventResultReference
     */
    public $reference;

    public function __construct($table, AdapterInterface $adapter)
    {
        if (!Helper::checkTableExistance($adapter, $table . '_table_page_logger')) {
            $filePath = 'data/table_page_logger/structure.sql';
            Helper::installDb($adapter, $table, $filePath);
        }
        $table .= '_table_page_logger';

        parent::__construct($table, $adapter);
        $this->reference = new EventResultReference($adapter);

    }

    public function addEvent($entityId, $eventType, $eventResult, $eventResultReference = null)
    {
        $eventResultReferenceId = null;
        if ($eventResultReference) {
            $eventResultReferenceId = $this->getReferenceId($eventResultReference);
        }
        $this->insert(['entity_id' => $entityId, 'event_type' => $eventType, 'event_result' => $eventResult, 'event_result_reference_id' => $eventResultReferenceId]);
    }

    public function getReferenceId($eventResultReference)
    {
        return $this->reference->addReference($eventResultReference);
    }

}