<?php
/**
 * Created by WebExperiment.
 * User: dom
 * Date: 23.07.18
 * Time: 14:06
 */

namespace Parser\Model\Configuration;


class ProductSyncable
{
    public const SYNCABLE_YES = 1;
    public const SYNCABLE_NO = 0;
    public const SYNCABLE_PREFOUND = 2;
    public const SYNCABLE_PRESYNCED = 3;
    public const SYNCABLE_BLACKLISTED = 4;
    public const SYNCABLE_DELETED = 5;

    public static function getOptionsForSelect($selected = ''): array
    {
        $options = self::getOptions();
        $data = [0 => ['value' => -1, 'title' => '', 'selected' => '']];
        foreach ($options as $key => $option) {
            $item = ['value' => $key, 'title' => $option];
            $item['selected'] = $key == $selected ? 'selected' : '';
            $data[] = $item;
        }

        return $data;
    }

    public static function getOptions(): array
    {
        return [
            self::SYNCABLE_YES => 'Active',
            self::SYNCABLE_NO => 'Inactive',
            self::SYNCABLE_PREFOUND => 'Check Variations',
            self::SYNCABLE_PRESYNCED => 'Move to Active?',
            self::SYNCABLE_BLACKLISTED => 'Blacklisted',
            self::SYNCABLE_DELETED => 'Deleted',
        ];
    }

    public static function getOptionsStrToLower() :array{
        $data = self::getOptions();
        $data = array_map('strtolower', $data);
        return $data;
    }
}