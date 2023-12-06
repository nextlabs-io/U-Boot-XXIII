<?php
/**
 * Created by WebExperiment.
 * User: dom
 * Date: 27.07.18
 * Time: 20:22
 */

namespace Parser\Model\Helper;

use Parser\Model\SimpleObject;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Where;

/**
 * Class Logger
 * @package Parser\Model
 *
 * The logger is designed to store simple logging messages in the database, it is useful to store some critical or statistical messages.
 * For example: you want to check which asins has some certain html structure. You can log occurances of this structure with asin=tag and with some known message
 * db structure: varchar index tag, varchar message, datetime created
 * CREATE TABLE `api_parser`.`logger` (
 * `logger_id` INT( 6 ) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
 * `tag` VARCHAR( 30 ) NOT NULL ,
 * `data` VARCHAR( 255 ) NULL ,
 * `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ,
 * INDEX ( `tag` )
 * );
 */
class Logger extends SimpleObject
{
    private $db;
    private $config;
    private $enabled = false;

    public function __construct($db, $config)
    {
        $this->config = $config;
        $this->db = $db;
        if (isset($this->config['logger']) && $this->config['logger']) {
            $this->enabled = true;
        }
    }

    public function add($tag, $msg)
    {
        if (! $this->enabled) {
            return false;
        }
        if (! $tag) {
            return false;
        }
        if (strlen($tag) > 30) {
            $tag = substr($tag, 0, 30);
        }
        if (strlen($msg) > 255) {
            $msg = substr($msg, 0, 255);
        }

        $sql = new Sql($this->db);
        $insert = $sql->insert('logger')
            ->values(['tag' => $tag, 'data' => $msg, 'created' => new Expression('NOW()')]);
        $stmt = $sql->prepareStatementForSqlObject($insert);
        return $stmt->execute();
    }

    /**
     * @param $where
     * @return array
     */
    public function getList($where): array
    {
        $sql = new Sql($this->db);
        $select = $sql->select('logger');
        $select->where($where)
            ->order('created DESC')
            ->limit(100);

        $stmt = $sql->prepareStatementForSqlObject($select);
        $result = $stmt->execute();
        $list = [];
        while($item = $result->current()){
            $list[] = $item;
            $result->next();
        }
        return $list;
    }

    public static function cleanLogs($db): void
    {
        $sql = new Sql($db);
        $delete = $sql->delete('logger');
        $where = new Where();
        $where->lessThan('created', new Expression('DATE_SUB(NOW(), INTERVAL 2 DAY)'));
        $delete->where($where);
        $stmt = $sql->prepareStatementForSqlObject($delete);
        $stmt->execute();
    }
}