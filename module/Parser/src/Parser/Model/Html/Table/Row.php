<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 20.07.2020
 * Time: 21:30
 */

namespace Parser\Model\Html\Table;


use Parser\Model\Html\Tag;

class Row
{
    public static function html($items, $type = 'td', $options = [])
    {
        $list = [];
        foreach ($items as $key => $item) {
            $list[] = Cell::html($item['content'] ?? '', $type, $item['options'] ?? []);
        }
        $content = implode('', $list);
        return Tag::html($content, 'tr', $options);
    }
}