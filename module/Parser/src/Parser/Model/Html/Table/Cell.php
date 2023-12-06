<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 20.07.2020
 * Time: 21:18
 */

namespace Parser\Model\Html\Table;


use Parser\Model\Html\Tag;

class Cell
{


    public static function html($content, $tag = 'td',  $options = []){
        $tag = $tag !== 'td' ? 'th' : 'td';
        return Tag::html($content, $tag, $options);
    }

}