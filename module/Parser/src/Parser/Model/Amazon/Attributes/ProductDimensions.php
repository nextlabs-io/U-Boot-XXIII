<?php
/**
 * Created by WebExperiment.
 * User: dom
 * Date: 02.08.18
 * Time: 15:05
 */

namespace Parser\Model\Amazon\Attributes;

use Parser\Model\Helper\Helper;

class ProductDimensions extends SimpleAttribute
{
    public function __construct($config, $xpath)
    {
        parent::__construct($config, $xpath, 'productDimensions');
    }

    public function extract()
    {
        $data = parent::extract();
        if (is_array($this->usedPath) && count($this->usedPath) && $data) {
            $dimensions = $this->usedPath['dimensions'] ?? 'cm';
            $data = str_replace(explode(',', $dimensions), '=delimiter=', $data);
            if (strpos($data, '=delimiter=')) {
                $chunks = explode('=delimiter=', $data);
                $data = $chunks[0];

                if (isset($this->usedPath['skip'])) {
                    $skips = explode(',', $this->usedPath['skip']);
                    if (count($skips)) {
                        $data = str_replace($skips, '', $data);
                    }
                }
                $delimiter = $this->usedPath['delimiter'] ?? 'x';
                $data = explode($delimiter, $data);
                if (count($data) == 3) {
                    $dimension = 1;
                    foreach ($data as $value) {
                        $dimension *= Helper::getFloat(trim($value), $this->config['productPage']);
                    }
                    return $dimension;
                }
            }
        }
        return null;
    }
}