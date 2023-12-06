<?php

namespace Parser\Model\Html;


use Laminas\View\Model\ViewModel;

/**
 * Copyright WebExperiment.info
 * Created by ernazar.
 * Date: 16.11.2017
 * Time: 18:23
 */
class Paging
{
    public $_currentPage;
    public $_countPages;
    public $_itemsOnPage;
    public $_countTotalItems;
    public $_pagesArray;

    public function __construct($currentPage, $countTotalItems, $itemsOnPage)
    {
        // Fix possible problems with variables
        $itemsOnPage = abs($itemsOnPage);
        if ($countTotalItems > 0) {
            $countPages = (int)(($countTotalItems - 1) / $itemsOnPage) + 1;
        } else {
            $countPages = 0;
        }
        if ($currentPage < 1) {
            $currentPage = 1;
        } else {
            if ($currentPage > $countPages) {
                $currentPage = $countPages;
            }
        }

        $this->_currentPage = $currentPage;
        $this->_countTotalItems = $countTotalItems;
        $this->_itemsOnPage = $itemsOnPage;
        $this->_countPages = $countPages;

        $borders = 2;
        $window = $borders * 2 + 1;
        $maxPagesWithoutHoles = $window * 3 + $borders * 2 * 2;

        $center = [];
        $right = [];
        if ($currentPage > 1) {
            $left = [0 => ["Title" => "Previous", "Page" => $currentPage - 1]];
        } else {
            $left = [0 => ["Title" => "Previous", "Page" => 1, "Selected" => 1]];
        }

        if ($countPages > $maxPagesWithoutHoles) {
            if ($countPages - $currentPage < $borders + 1) {
                $start = $countPages - $window + 1;
            } else {
                if ($currentPage > $borders + 1) {
                    $start = $currentPage - $borders;
                } else {
                    $start = 1;
                }
            }

            if ($window < $countPages && $currentPage + $borders < $window) {
                $end = $window;
            } // we have to show at least first 5 pages in left
            else {
                if ($currentPage + $borders < $countPages) {
                    $end = $currentPage + $borders;
                } else {
                    $end = $countPages;
                }
            }

            // Define center part of the paging
            for ($i = $start; $i < $end + 1; $i++) {
                if ($currentPage == $i) {
                    array_push($center, ["Title" => $i, "Page" => $i, "Selected" => 1]);
                } else {
                    array_push($center, ["Title" => $i, "Page" => $i]);
                }
            }

            // Define left part of the paging
            if ($start < $window * 2 + 1 && $start > 0) {
                for ($i = 1; $i < $start; $i++) {
                    array_push($left, ["Title" => $i, "Page" => $i]);
                }
            } else {
                for ($i = 1; $i < $window + 1; $i++) {
                    array_push($left, ["Title" => $i, "Page" => $i]);
                }
                $leftHole = (int)(($window + 1 + $start) / 2);
                array_push($left, ["Title" => "...", "Page" => $leftHole]);
            }

            // Define right part of the paging
            if ($end > $countPages - $window * 2) {
                for ($i = $end + 1; $i < $countPages + 1; $i++) {
                    array_push($right, ["Title" => $i, "Page" => $i]);
                }
            } else {
                $rightHole = intval(($countPages - $window + 1 + $end) / 2);
                array_push($right, ["Title" => "...", "Page" => $rightHole]);
                for ($i = $countPages - $window + 1; $i < $countPages + 1; $i++) {
                    array_push($right, ["Title" => $i, "Page" => $i]);
                }
            }
        } else {
            for ($i = 1; $i < $countPages + 1; $i++) {
                if ($currentPage == $i) {
                    array_push($center, ["Title" => $i, "Page" => $i, "Selected" => 1]);
                } else {
                    array_push($center, ["Title" => $i, "Page" => $i]);
                }
            }
        }

        if ($currentPage < $countPages) {
            array_push($right, ["Title" => "Next", "Page" => $currentPage + 1]);
        } else {
            array_push($right, ["Title" => "Next", "Page" => $currentPage, "Selected" => 1]);
        }

        $result = array_merge($left, $center, $right);
        if (count($result) > 2) {
            $this->_pagesArray = array_merge($left, $center, $right);
        } else {
            $this->_pagesArray = [];
        }
    }

    public function getCountPages()
    {
        return $this->_countPages;
    }

    public function getAsHTML()
    {
        $this->getAsArray();
        $from = ($this->getCurrentPage() - 1) * $this->_itemsOnPage + 1;
        $to = $from + $this->_itemsOnPage - 1;
        if ($to >= $this->_countTotalItems) {
            $to = $this->_countTotalItems - 1;
        }
        if ($from < 0) {
            $from = 0;
        }
        if ($to < 0) {
            $to = 0;
        }

        $grid = new ViewModel([
            'pages' => $this->getAsArray(),
            'from' => $from,
            'to' => $to,
            'totalItems' => $this->_countTotalItems,
        ]);
        $grid->setTemplate('helper/paginator');
        return $grid;
    }

    public function getAsArray()
    {
        if (count($this->_pagesArray) > 1) {
            $this->_pagesArray[0]['First'] = 1;
            $this->_pagesArray[count($this->_pagesArray) - 1]['Last'] = 1;
            foreach ($this->_pagesArray as $k => $v) {
                $this->_pagesArray[$k]["URL"] = "javascript:changePage(" . $v["Page"] . ")";
            }
        }
        return $this->_pagesArray;
    }

    /**
     * @return int
     */
    public function getCurrentPage(): int
    {
        return $this->_currentPage;
    }

    /**
     * @param $selected
     * @return string
     */
    public function getPerPageSelectorDropdown($selected): string
    {
        $list = [20 => 20, 100 => 100, 500 => 500, 1000 => 1000];
        return Dropdown::getHtml($list, $selected,
            [
                'name' => 'filter[per-page]',
                'aria-controls' => 'datatable-responsive',
                'class' => 'form-control input-sm',
                'id' => 'per-page',
            ], ['no-default-value' => 1]);
    }
}

