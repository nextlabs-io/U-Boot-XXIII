<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 16.12.2018
 * Time: 12:29
 */

namespace Parser\Model\Html;


class Dropdown
{
    /**
     * @param $list
     * @param $selected
     * @param $attributes
     * @return string
     */
    public static function getHtml($list, $selected = null, $attributes = [], $options = [])
    {
        $select = new self();
        $html = '';
        if (!isset($options['no-default-value'])) {
            $html .= Tag::html('&nbsp;', 'option', ['value' => '']);
        }
        if (is_array($list) and count($list)) {
            foreach ($list as $key => $item) {
                $html .= $select->renderOption($key, $item, $selected);
            }
        }
        $html = Tag::html($html, 'select', $attributes);

        return $html;
    }

    private function renderOption($key, $item, $selected)
    {
        $options = ['value' => $key];
        if($selected == $key){
            $options['selected'] = 'selected';
        }
        return Tag::html($item, 'option', $options);
    }
}