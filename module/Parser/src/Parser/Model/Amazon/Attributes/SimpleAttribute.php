<?php
/**
 * Created by WebExperiment.
 * User: dom
 * Date: 02.08.18
 * Time: 14:13
 *
 * The class is designed to extract certain attribute from the certain html structure.
 * The html structure might be different from item to item and the class handles this.
 */

namespace Parser\Model\Amazon\Attributes;


use Parser\Model\Helper\Helper;
use Parser\Model\SimpleObject;

class SimpleAttribute extends SimpleObject
{
    public $config;
    /**
     * @var $xpath \DOMXPath
     */
    public $xpath;
    public $label;
    public $usedPath;
    public $string;
    public $allPathsData;

    public function __construct($config, $xpath, $label)
    {
        $this->config = $config;
        $this->xpath = $xpath;
        $this->label = $label;
        $this->usedPath = [];
    }

    public function extract()
    {
        if (!isset($this->config['attributes'][$this->label])) {
            $this->addError('missing configuration section:' . $this->label);
            return '';
        }
        $paths = $this->config['attributes'][$this->label];
        $attribute = '';
        // there might be multiple html structures we need to figure out first which gives results
        $possibleData = [];
        foreach ($paths as $id => $path) {
            switch ($path['type']) {
                case 'ul':
                    $possibleData[$id] = $this->fromUl($path);
                    break;
                default:
                    $possibleData[$id] = $this->fromTable($path);
                    break;
            }
//            if ($attribute) {
//                $this->usedPath = $path;
//                break;
//            }
        }
        $this->allPathsData = $possibleData;
        foreach ($possibleData as $id => $attribute) {
            if ($attribute) {
                $this->usedPath = $paths[$id];
                $this->string = $attribute;
                if(strpos($attribute, ';') !== false){
                    break;
                }
            }
        }
//        pr($possibleData);
//        pr($this->string);
        return $this->string;
    }

    private function fromUl($path)
    {
        $container = $path['containerpath'];
        $label = $path['label'];
        $replaces = $path['replaces'] ?? null;
        if ($replaces) {
            $replaces = explode(' ', $replaces);
        }
        $res = $this->xpath->query($container);
        if (count($res)) {
            foreach ($res as $element) {
                $string = $element->textContent;
                if (strpos($string, $label) !== false) {
                    $data = str_replace($label, '', $string);
                    if ($replaces) {
                        $data = str_replace($replaces, '', $data);
                    }
                    $data = trim($data);
                    return $data;
                }
            }
        }
    }

    private function fromTable($path)
    {
        $attribute = TableAttribute::extract($this->xpath, $path['label'], $path['containerpath'], $path['labelpath'],
            $path['valuepath']);
        $replaces = $patp['replaces'] ?? null;
        if ($replaces) {
            $replaces = explode(' ', $replaces);
            $attribute = str_replace($replaces, '', $attribute);
        }
        return $attribute;
    }

    /**
     * convert weight string to gram
     * @param array $data
     * @return float|int|null
     */

    public function getWeightData($data)
    {
        if (is_array($data) && count($data)) {
            foreach ($data as $item) {
                if ($item = trim($item)) {
                    if (strpos($item, 'Kg') !== false) {
                        $item = str_replace('Kg', '', $data);
                        $weight = Helper::getFloat($item, $this->config['productPage']);
                        $weight *= 1000;
                    } elseif (strripos($item, 'grams') !== false) {
                        $item = strtolower($item);
                        $item = str_replace('grams', '', $item);
                        $weight = Helper::getFloat($data, $this->config['productPage']);
                    } else {
                        $item = str_replace('g', '', $item);
                        $weight = Helper::getFloat($item, $this->config['productPage']);
                    }
                    if ($weight) {
                        return $weight;
                    }
                }
            }
        }
        return null;
    }

    /**
     * @param $dimData string 'w:12.9133858136; h:3.7795275552; l:17.3228346280; wei:2.5573622392'
     * @return array
     */
    public static function getDimensionFromCentric($dimData): array
    {
        $result = [];
        $mask = ['wei' => 'shipping_weight', 'w' => 'shipping_width', 'h' => 'shipping_height', 'l' => 'shipping_length'];
        $divider = ['wei' => 0.45, 'w' => 2.54, 'h' => 2.54, 'l' => 2.54];
        if ($dimData) {
            $dimData = explode(';', $dimData);
            foreach ($dimData as $value) {
                $pair = explode(':', $value);
                $pair[0] = trim($pair[0]);
                if (isset($pair[1], $mask[$pair[0]]) && $pair[1]) {
                    $result[$mask[$pair[0]]] = $divider[$pair[0]] * $pair[1];
                }
            }
        }
        return $result;
    }

    public static function getDimensionFromHtml($dimension)
    {
        // todo move measures to config
        // 39.4 x 30.5 x 5.7 cm; 130 Grams, inches, or something else?
        $measures = [' cm' => 1, ' inch' => 2.54, ' centimetres' => 1];
        foreach ($measures as $mark => $value) {
            if (strpos($dimension, $mark) !== false) {
                $dimArray = explode($mark, $dimension);
                // '39.4 x 30.5 x 5.7'
                $dimData = trim($dimArray[0]);
                $dimData = str_replace([' ', ','], ['', '.'], $dimData);
                $dimArray = explode('x', $dimData);
                if (count($dimArray) === 3) {
                    arsort($dimArray);
                    $converted = array_map(
                        static function ($size) use ($value) {
                            return $value * (float)$size;
                        },
                        $dimArray);
                    return array_combine(['shipping_length', 'shipping_width', 'shipping_height'], $converted);
                }
            }
        }
        return [];
    }

    public static function getWeightFromHtml($weightString)
    {
        // '1,2 Kg' or '200 g'
        // a:3:{s:6:"weight";s:5:"159 g";s:15:"shipping_weight";s:40:"159 g (View shipping rates and policies)";s:9:"dimension";s:20:"39.4 x 30.5 x 5.7 cm; 130 Grams";}
        // possible weight g, Kg, ounces, ounce, pounds
        // possible size cm inches
        // todo move measures to config
        $measures = [' Kg' => 1, ' ounce' => 0.02834952, ' pound' => 0.45, ' g' => 0.001, ' Grams' => 0.001, ' Kilograms' => 1];
        foreach ($measures as $mark => $value) {
            if (strpos($weightString, $mark) !== false) {
                $weightArray = explode($mark, $weightString);
                // '1 234,5 Kg ?? '
                $weight = trim($weightArray[0]);
                $weight = str_replace([' ', ','], ['', '.'], $weight);
                return (float)$weight * $value;
            }
        }
        return null;
    }
}