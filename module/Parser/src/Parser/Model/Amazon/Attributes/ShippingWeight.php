<?php
/**
 * Created by WebExperiment.
 * User: dom
 * Date: 02.08.18
 * Time: 15:06
 */

namespace Parser\Model\Amazon\Attributes;

use Parser\Model\Helper\Helper;

class ShippingWeight extends SimpleAttribute
{

    public function __construct($config, $xpath)
    {
        parent::__construct($config, $xpath, 'shippingWeight');
    }

    public function extract()
    {
        $data = parent::extract();
        return $this->getWeightData($data);

    }


}