<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 23.07.2020
 * Time: 18:20
 */

namespace BestBuy\Model\BestBuy;


class Helper
{
    public static function getBBProductFromUrl($url)
    {
        // sample /en-ca/product/otterbox-commuter-fitted-hard-shell-ca/13863823
        // id is 13863823

        if($url){
            $first = explode('?', $url);
            $parts = explode('/', $first[0]);
            return end($parts);
        }
        return null;

    }

    public static function cutLiteralString(String $string, Int $length)
    {
        if ($length < 4) {
            return $string;
        }

        if (strlen($string) > $length) {
            $string = mb_substr($string, 0, $length - 3);
            if (strpos(' ', $string) !== false) {
                $list = explode(' ', trim($string));
                array_pop($list);
                $string = implode(' ', $list);
            }
            return $string . '...';
        }
        return $string;
    }

    public static function directCut($string, $limit)
    {
        if ($limit && strlen($string) > $limit) {
            $string = mb_substr($string, 0, $limit);
        }
        return $string;
    }

    public static function directExtract($data, array $sequence, Int $limit = null)
    {
        foreach ($sequence as $fieldName) {
            $fieldVal = $data[$fieldName] ?? '';
            if ($fieldVal) {
                return self::directCut($fieldVal, $limit);
            }
        }
        return '';
    }

    public static function literalExtract($data, array $sequence, Int $limit = null)
    {
        foreach ($sequence as $fieldName) {
            $fieldVal = $data[$fieldName] ?? '';
            if ($fieldVal) {
                return self::cutLiteralString($fieldVal, $limit);
            }
        }
        return '';
    }

    public static function replace4byte($string, $replacement = '') {
        return preg_replace('%(?:
          \xF0[\x90-\xBF][\x80-\xBF]{2}      # planes 1-3
        | [\xF1-\xF3][\x80-\xBF]{3}          # planes 4-15
        | \xF4[\x80-\x8F][\x80-\xBF]{2}      # plane 16
    )%xs', $replacement, $string);
    }
}