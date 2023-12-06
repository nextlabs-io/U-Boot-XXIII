<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 16.02.2020
 * Time: 13:44
 */

namespace Parser\Model\Html;

use Parser\Model\SimpleObject;

// class to store items in debug mode and to ability to provide all content.
class ContentCollector extends SimpleObject
{
    private $basePath;
    private $debugMode;

    public function __construct($basePath, $debugMode)
    {
        $this->basePath = $basePath;
        $this->debugMode = $debugMode;
    }

    /**
     * @param $content
     * @param $tag
     * @return bool|int
     * @throws \Exception
     */
    public function saveFile($content, $tag)
    {
        if ($this->debugMode) {
            $this->prepareFilePath($tag);
            $filePath = $this->basePath . '/' . $tag;
            return file_put_contents($filePath, $content);
        }
        return false;
    }

    /**
     * @param $tag
     * @throws \Exception
     */
    public function prepareFilePath($tag)
    {
        if (strpos($tag, '/') !== false) {
            $pathParts = explode('/' , $tag);
            array_pop($pathParts);
            $filePath = $this->basePath . '/' . implode('/', $pathParts);
            if (!is_dir($filePath) && !mkdir($filePath, 0755, true) && !is_dir($filePath)) {
                throw new \Exception(sprintf('Directory "%s" was not created', $filePath));
            }
        }
    }

    public function getFile($tag)
    {
        $filePath = $this->basePath . '/' . $tag;
        return is_file($filePath) ? file_get_contents($filePath) : false;
    }


}