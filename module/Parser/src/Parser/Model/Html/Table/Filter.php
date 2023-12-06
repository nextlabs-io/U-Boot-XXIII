<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 13.12.2018
 * Time: 21:11
 */

namespace Parser\Model\Html\Table;


class Filter
{
    public static $js = '';
    public static $input = '';
    public static function html($fields, $rowOptions = [] )
    {
        // filter is a part of the table
        return Row::html($fields, 'td', $rowOptions);
    }

    public static function getJS($filter)
    {
        return $filter['scripts'] ?? '';
    }

    public static function getInput($filter)
    {
        return $filter['inputs'] ?? '';
    }
}