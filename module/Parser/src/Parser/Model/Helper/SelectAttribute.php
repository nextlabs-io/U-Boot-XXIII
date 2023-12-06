<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 22.06.2020
 * Time: 20:18
 */

namespace Parser\Model\Helper;


class SelectAttribute
{

    public $options = [];

    public function getOptionsForSelect($selected = '', $noTypeTitle = ''): array
    {
        $options = $this->getOptions();
        $data = [
            0 => [
                'value' => -1,
                'title' => $noTypeTitle,
                'selected' => ($selected == '' || $selected == -1) ? 'selected' : '',
            ],
        ];
        foreach ($options as $key => $option) {
            $item = ['value' => $key, 'title' => $option];
            $item['selected'] = $key == $selected ? 'selected' : '';
            $data[] = $item;
        }

        return $data;
    }

    public function getOptions(): array
    {
        $list = [];

        foreach ($this->options as $option) {
            $list[$option] = $option;
        }
        return $list;
    }
}