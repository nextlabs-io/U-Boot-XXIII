<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 13.12.2018
 * Time: 21:12
 */

namespace Parser\Model\Html\Table;


class Sort
{
    public static $js = '    
    $(".column-title").click(function (event) {
        if (event.target.id) {
            let element = $(\'#\' + event.target.id);
            let currentSortColumn = $(\'#sort_column\').val();
            let currentSortType = $(\'#sort_type\').val();
            let type = \'desc\';
            if (element.attr(\'data-row\') === currentSortColumn) {
                type = currentSortType === \'asc\' ? \'desc\' : \'asc\';
                $(\'#sort_type\').val(type);
            } else {
                $(\'#sort_type\').val(type);
                $(\'#sort_column\').val(element.attr(\'data-row\'));
            }
            $(\'#action-form\').submit();
        }
    });
    ';
    public static $input = '
<input type="hidden" name="filter[sort_column]" id="sort_column" value="{sort_column}"/>
<input type="hidden" name="filter[sort_type]" id="sort_type" value="{sort_type}"/>
                ';

    // render clickable html block which changes order
    public static function html($fields, $order, $default = [])
    {

    }

    public static function getJS($filter)
    {
        return self::$js;
    }

    public static function getInput($filter)
    {
        $string = str_replace('{sort_column}', $filter['sort_column'] ?? '', self::$input);
        $string = str_replace('{sort_type}', $filter['sort_type'] ?? '', $string);
        return $string;
    }
}