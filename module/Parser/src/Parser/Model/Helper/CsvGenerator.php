<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 03.02.2019
 * Time: 16:15
 */

namespace Parser\Model\Helper;


class CsvGenerator
{

    /** @var $csvMain array of base fields */
    private $csvMain;
    private $delimiter = ';';
    private $escaper = '"';

    public function __construct($csvMain, $options = [])
    {
        $this->csvMain = $csvMain;
        if (isset($options['delimiter'])) {
            $this->delimiter = $options['delimiter'];
        }
        if (isset($options['escaper'])) {
            $this->escaper = $options['escaper'];
        }

    }

    /**
     * @return string gives csv first line with field list
     */
    public function generateHeader()
    {
        $fields = [];
        foreach ($this->csvMain as $key => $field) {
            $fields[] = $key;
        }
        return $this->implodeData($fields);
    }

    /**
     * @param $fields
     * @return string
     */
    public function implodeData($fields)
    {
        $search = ['"', "\n"];
        $replace = ['""', ''];
        if ($this->delimiter) {
            $search[] = $this->delimiter;
            $replace[] = '\\' . $this->delimiter;
        }
        foreach ($fields as $key => $item) {
            $fields[$key] = $this->escaper
                . str_replace($search, $replace, $item)
                . $this->escaper;

        }
        return rtrim(implode($this->delimiter, $fields)). "\n";
    }

    /**
     * @param array $fields
     * @return bool|string
     */
    public function csvStr(array $fields)
    {
        $f = fopen('php://memory', 'r+');
        if ($this->fPutCsv($f, $fields, $this->delimiter, $this->escaper) === false) {
            return false;
        }
        rewind($f);
        $csv_line = stream_get_contents($f);
        return rtrim($csv_line);
    }

    /**
     * @param        $handle
     * @param        $row
     * @param string $fd
     * @param string $quot
     * @return int
     */
    public function fPutCsv($handle, $row, $fd = ',', $quot = '"')
    {
        $str = '';
        foreach ($row as $cell) {
            $cell = str_replace([$quot, "\n"],
                [$quot . $quot, ''],
                $cell);
            if (false !== strpos($cell, $fd) || false !== strpos($cell, $quot)) {
                $str .= $quot . $cell . $quot . $fd;
            } else {
                $str .= $cell . $fd;
            }
        }
        fwrite($handle, substr($str, 0, -1) . "\n");
        return strlen($str);
    }

    /**
     * @param $main
     * @return string
     * TODO may be change the logic indicating which data we are using one or another instead of placing two data objects. The way it is now is a little bit confusing
     */
    public function renderLine($main)
    {
        $fields = [];
        $mainKeys = $this->csvMain;
        foreach ($mainKeys as $key => $field) {
            $fields[$key] = isset($main[$field]) ? $main[$field] : '';
        }
        return $this->implodeData($fields);
    }


}