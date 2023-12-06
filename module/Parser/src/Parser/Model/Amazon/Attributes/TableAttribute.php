<?php
/**
 * Created by WebExperiment.
 * User: dom
 * Date: 02.08.18
 * Time: 14:48
 */

namespace Parser\Model\Amazon\Attributes;


class TableAttribute
{

    public static function extract(\DOMXPath $xpath, $label, $containerPath, $labelPath, $valuePath)
    {

        $res = $xpath->query($containerPath);

        $labels = [];
        $values = [];
        foreach ($res as $element) {
            $labelRes = $xpath->query($labelPath, $element);
            $valueRes = $xpath->query($valuePath, $element);
            if (count($valueRes)) {
                foreach ($labelRes as $item) {
                    $labels[] = trim($item->textContent);
                }
                foreach ($valueRes as $item) {
                    $values[] = trim($item->textContent);
                }
            }
        }
//        if($containerPath == ".//div[@id='productOverview_feature_div']//tr"){
//            pr($labels);
//            pr($values);
//            pr($res);die();
//        }

        foreach ($labels as $key => $value) {
            if (strpos($value, $label) !== false && isset($values[$key])) {
                return $values[$key];
            }
        }
        return '';
    }

}