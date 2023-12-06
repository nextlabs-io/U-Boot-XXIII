<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 20.07.2020
 * Time: 16:21
 */

namespace Parser\Model\Html;

// sample class to generate header, columns, to order and to filter
use Parser\Controller\AbstractController;
use Parser\Model\Html\Paging;
use Parser\Model\Html\Table\Filter;
use Parser\Model\Html\Table\Row;
use Parser\Model\Html\Table\Sort;
use Westsworld\TimeAgo;
use Laminas\Mvc\Controller\ControllerManager;


/**
 * Each field can be altered in html by creating getHtmlFieldId function and getOptionsFieldId function
 * Class HtmlList
 * @package Parser\Model\Html
 */
abstract class HtmlList
{
    public $filterFields = [];
    public $orderFields = [];
    /**
     * @var $fields = [
     * 'Unique-id' => [
     * 'field' => 'id-in-the-item-list',
     * 'title' => 'Id',
     * 'options' => ['width' => '3%', 'id' => 'row_category_best_buy_id', 'data-row' => 'l.category_best_buy_id'],
     * 'item_attribute' => '',
     * ],
     * ]
     *
     */
    public $fields = [];
    public $page;
    public $itemsPerPage;
    public $uniqueIdField;
    public $actions = [];
    public $paging;
    public $scripts = [];
    public $inputs = [];
    /**
     * @var null|AbstractController
     */
    public $controller;



    public function __construct($page, $countTotalItems, $itemsPerPage, $controller = null)
    {
        // generates a table with title, sorting, filtering.
        // additionaly you can generate paging.
        $this->paging = new Paging($page, $countTotalItems, $itemsPerPage);
        if($controller) {
            $this->controller = $controller;
        }

    }

    /**
     * @param $itemList
     * @param $filter
     * @param array $tableOptions
     * @param string $noItemsLabel
     * @return string|string[]
     * @throws \yii\db\Exception
     */
    public function getTable($itemList, $filter, $tableOptions = ['class' => "table table-striped jambo_table bulk_action dataTable products",
        'role' => "grid", 'style' => "width: 100%;",
        'width' => "100%",
        'cellspacing' => "0"], $noItemsLabel = 'no items found')
    {
        // note count fields, sortfields, orderfields should be the same

        $tableRows = [];
        $tableHead = '';
        if ($this->fields) {
            if ($this->orderFields) {
                foreach ($this->orderFields as $k => $item) {
                    $this->fields[$k]['options']['class'] .= ' column-title';
                }
                $this->scripts[] = Sort::getJS($filter);
                $this->inputs[] = Sort::getInput($filter);
            }
            if ($this->filterFields) {
                foreach ($this->filterFields as $k => $item) {
                    $this->fields[$k]['title'] .= $item;
                }
                $this->scripts[] = Filter::getJS($filter);
                $this->inputs[] = Filter::getInput($filter);
            }

            foreach ($this->fields as $k => $item) {
                $this->fields[$k]['content'] = $item['title'];
            }
            $headerContent = Row::html($this->fields, 'th', ['role' => 'row']);
            $tableHead = Tag::html($headerContent, 'thead');


            if ($itemList) {
                $chereda = 'even';
                foreach ($itemList as $k => $item) {
                    // item just have fields taken from the db, we need to fill it with fields
                    $chereda = $chereda === 'odd' ? 'even' : 'odd';
                    $preparedFields = $this->prepareFieldsForItem($item);
                    $tableRows[] = Row::html($preparedFields, 'td', ['role' => 'row', 'class' => $chereda]);

                }
            } else {
                $tableRows[] = Row::html([0 => ['content' => $noItemsLabel, 'options' => ['align' => 'center', 'colspan' => count($this->fields)]]], 'td', ['role' => 'row', 'class' => '', ]);
            }
        }
        $tableBody = Tag::html(implode("\r\n", $tableRows), 'tbody');
        $tableContent = $tableHead . $tableBody;

        $table = Tag::html($tableContent, 'table', $tableOptions);
        if ($this->scripts) {
            $table .= Tag::html(implode("\r\n", $this->scripts), 'script', ['type' => 'text/javascript']);
        }
        if ($this->inputs) {
            $table .= implode("\r\n", $this->inputs);
        }
        return $table;

    }

    /**
     * @param $item
     * @return array
     */
    protected function prepareFieldsForItem($item): array
    {
        $listToGo = [];
        foreach ($this->fields as $k => $field) {
            $data = ['content' => '', 'options' => []];
            $value = '';
            $fieldId = $field['field'] ?? null;
            if ($fieldId && isset($item[$fieldId])) {
                // we can put something to the content, otherwise content is empty.
                $value = $item[$fieldId];
            }
            $methodName = 'getHtml' . self::getMethodName($fieldId);
            $optionsMethodName = 'getOptions' . self::getMethodName($fieldId);
            if (method_exists(get_class($this), $methodName)) {
                $value = $this->$methodName($item, $fieldId);
            }
            if (method_exists(get_class($this), $optionsMethodName)) {
                $data['options'] = $this->$optionsMethodName($item, $fieldId);
            } else {
                $data['options'] = $field['item_options'] ?? [];
            }
            $data['content'] = $value;

            $listToGo[$k] = $data;
        }
        return $listToGo;
    }

    /**
     * @param $string
     * @return string
     */
    protected static function getMethodName($string): string
    {
        $list = explode('_', $string);
        $list = array_map('strtolower', $list);
        $list = array_map('ucfirst', $list);
        return implode('', $list);
    }


    protected function getAligCenter()
    {
        return ['align' => 'center'];
    }


    protected function timeAgoDate($item, $fieldId)
    {
        $timeago = new TimeAgo();
        return $timeago->inWords($item[$fieldId]);
    }

}