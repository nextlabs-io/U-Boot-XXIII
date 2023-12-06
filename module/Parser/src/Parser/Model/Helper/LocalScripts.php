<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 11.01.2019
 * Time: 17:52
 */

namespace Parser\Model\Helper;

/**
 * Class LocalScripts
 * @package Parser\Model\Helper
 * get localfiles in the local folder cache them if needed and deploy
 */
class LocalScripts
{
    public static function get()
    {
        $dir = 'data/LocalScripts/';
        if (! dir($dir)) {
            return false;
        }
        $list = scandir($dir, SCANDIR_SORT_NONE);
        $itemsHead = [];
        $itemsBody = [];
        if (count($list)) {
            foreach ($list as $file) {
                if (strpos($file, '.html') !== false) {
                    // TODO need to cache result if no files changed.
                    $fileStamp = filemtime($dir . $file);
                    if (strpos($file, '.head') !== false) {
                        $itemsHead[] = file_get_contents($dir . $file);
                    } elseif (strpos($file, '.body') !== false) {
                        $itemsBody[] = file_get_contents($dir . $file);
                    }
                }
            }
        }
        return [
            'localScriptsHead' => implode("\n", $itemsHead),
            'localScriptsBody' => implode("\n", $itemsBody),
        ];
    }
}