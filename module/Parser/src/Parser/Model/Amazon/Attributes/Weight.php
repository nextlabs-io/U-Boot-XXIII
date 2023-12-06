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

class Weight extends SimpleAttribute
{
    public function __construct($config, $xpath)
    {
        parent::__construct($config, $xpath, 'weight');
    }

    public function extract()
    {
        $data = parent::extract();
        return $this->getWeightData($data);

    }

}