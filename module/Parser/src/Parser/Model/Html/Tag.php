<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 20.07.2020
 * Time: 21:21
 */

namespace Parser\Model\Html;


use yii\db\Exception;

class Tag
{
    /**
     * Get basic tag with content and options.
     * @param $content
     * @param $tag
     * @param array $options
     * @param bool $short
     * @return string|string[]
     * @throws Exception
     */
    public static function html($content, $tag, $options = [], $short = false)
    {

        $item = $short ? '<{tag}{options}/>' :  '<{tag}{options}>' . $content . '</{tag}>';
        $item = str_replace('{tag}', $tag, $item);
        $optionsString = '';
        if(!is_array($options)){
            throw new Exception('not array in options');
        }
        if(count($options)) {
            foreach ($options as $key => $option) {
                $optionsString .= ' ' . $key . '="' . $option . '"';
            }
        }
        $item = str_replace('{options}', $optionsString, $item);
        return $item;
    }
}