<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 25.12.2020
 * Time: 20:18
 */

namespace Parser\Model\Helper;


class JsonHelper
{

    public static function generateJsonSchemaFromConfig($config){
        $schema = [];
        foreach ($config as $key => $item) {
            $itemSchema = ['title' => $key];
            if(is_array($item)){
                $itemSchema['type'] = 'object';
                $itemSchema['properties'] = self::generateJsonSchemaFromConfig($item);
            } else {
                if($item === 0 || $item === 1 || $item === '0' || $item === '1' || is_bool($item)){
                    $itemSchema['type'] = 'boolean';
                    $itemSchema['format'] = 'checkbox';
                } else {
                    $itemSchema['type'] = 'string';
                }

            }
            $schema[$key] = $itemSchema;

        }
        return $schema;
    }

    public static function prependJson($config){
        $schema = [];
        foreach ($config as $key => $item) {

            if(is_array($item)){
                $itemSchema = self::prependJson($item);
            } else {
                if(is_bool($item)){
                    $itemSchema = $item;
                }
                elseif($item === 1 || $item === '1'){
                    $itemSchema = true;
                }
                elseif($item === 0 || $item === '0')
                {
                    $itemSchema= false;
                } else {
                    $itemSchema = $item;
                }

            }
            $schema[$key] = $itemSchema;
        }
        return $schema;
    }

    public static function convertXmlToJson($xmlData, $schema){
        $newData = [];
        foreach ($xmlData as $key => $item){
            $itemSchema = $schema[$key] ?? [];
            $newItem = null;
            if(is_array($item)){
                $newItem = self::convertXmlToJson($item, $itemSchema['properties']);
            } else {
                $type = $itemSchema['type'] ?? null;
                if($type == 'boolean'){
                    $newItem = (bool) $item;
                } else {
                    $newItem = $item;
                }
            }
            $newData[$key] = $newItem;
        }
        return $newData;
    }

    /**
     * @param config file path for json $jsonConfigFile
     * @param $xmlConfigFile
     */
    public static function generateLocalJsonFromLocalXml($jsonConfigFile, $xmlConfigFile){
        $jsonLocalConfigFile = str_replace('.json', '.local.json', $jsonConfigFile);
        $xmlLocalConfigFile = str_replace('.xml', '.local.xml', $xmlConfigFile);
        $jsonSchemaFile = str_replace('.json', '-schema.json', $jsonConfigFile);
        if (!file_exists($jsonLocalConfigFile)
            && file_exists($xmlLocalConfigFile)) {

            if(!file_exists($jsonSchemaFile)){
                throw new \RuntimeException('no schema found for '. $jsonConfigFile);
            }
            // attempt to create one from local.xml first
//            $xmlConfigFile = 'data/parser/config/config.xml';
            $xmlConfig = Helper::loadConfig($xmlConfigFile, 'xml');
            $schema = json_decode(file_get_contents($jsonSchemaFile), 1);
            $newConfig = self::convertXmlToJson($xmlConfig, $schema);
            file_put_contents($jsonLocalConfigFile, json_encode($newConfig));
        }
    }

    public static function generateJsonSchemaAndConfigFromXml($xmlConfigFile, $jsonConfigFile){
        $jsonSchemaFile = str_replace('.json', '-schema.json', $jsonConfigFile);
        $xmlFullConfig = Helper::loadConfig($xmlConfigFile, 'xml');
        $schema = self::generateJsonSchemaFromConfig($xmlFullConfig);
        file_put_contents($jsonSchemaFile, json_encode($schema));

        $xmlBaseConfig = Helper::loadConfig($xmlConfigFile, 'xml', false);
        $newConfig = self::convertXmlToJson($xmlBaseConfig, $schema);
        file_put_contents($jsonConfigFile, json_encode($newConfig));
    }

}