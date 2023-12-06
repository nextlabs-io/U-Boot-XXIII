<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 11.09.2020
 * Time: 21:01
 */

namespace Parser\Model\Amazon\Html;


use Parser\Model\Html\Tag;
use Laminas\View\Helper\HtmlTag;

class Helper
{
    public static function getSimpleOnchangeSubmit($elemId, $formId)
    {
        return '
        $(\'#' . $elemId . '\').change(function (event) {
            $("#' . $formId . '").submit();
        });
        ';
    }

    public static function getPagingScript($inputId, $formId)
    {

        return '
    function changePage(page) {
        $(\'#' . $inputId . '\').val(page);
        $(\'#' . $formId . '\').submit();
    }
        ';
    }

    public static function getPageInput(string $elemId, string $elemName, $val)
    {
        return Tag::html('', 'input', ['value' => $val, 'type' => 'hidden', 'id' => $elemId, 'name' => $elemName], true);
    }

    public static function getCheckbox($filter, $fieldName)
    {
//        pr($filter);die();
        $descOptions = ['name' => 'filter[' . $fieldName . ']', 'value' => 1, 'class' => 'checkbox-'.$fieldName];
        if ($filter[$fieldName] ?? null) {
            $descOptions['checked'] = 'checked';
        }
        $descOptions['type'] = 'checkbox';
//        pr(Tag::html('', 'input', $descOptions, true));
        return Tag::html('', 'input', $descOptions, true);
    }
}