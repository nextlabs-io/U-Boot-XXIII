<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 02.12.2020
 * Time: 17:05
 */

namespace Parser\Model\Amazon;


interface CategoryPageInterface
{
    public function getPagesQty($categoryId);
    public function loadPageCandidate($categoryId = null);
    public function addPages(array $cleanPages, $categoryId);
}