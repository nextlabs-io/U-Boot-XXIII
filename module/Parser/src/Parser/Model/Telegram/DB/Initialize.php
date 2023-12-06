<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 26.10.2020
 * Time: 17:27
 */

namespace Parser\Model\Telegram\DB;


use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Sql;

class Initialize
{
    public static function check($db, $table)
    {
        $sql = new Sql($db);
        try {
            $select = $sql->select($table);
            $select->columns(['qty' => new Expression('COUNT(*)')]);
            $select->limit(1);
            $stmt = $sql->prepareStatementForSqlObject($select);
            $result = $stmt->execute();
            if ($result->current()) {
                return true;
            }
        } catch (\Exception $e) {

        }
        return false;
    }

    public static function installDb($db, $prefix)
    {
        $sql = new Sql($db);
        /* first delete old data */
        $stmt = $sql->getAdapter()->getDriver()->createStatement();

        $query = file_get_contents('data/telegram_bot/structure.sql');
        if($query){
            $query = str_replace('{TABLE_PREFIX}', $prefix, $query);
            $stmt->setSql($query);
            $stmt->execute();
        }
        else {
            throw new \Exception(' no basic structure found for the mysql ');
        }
    }
}