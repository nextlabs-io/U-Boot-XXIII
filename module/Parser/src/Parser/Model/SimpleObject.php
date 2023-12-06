<?php
/**
 * Created by WebExperiment.
 * User: ernazar
 * Date: 10.03.2016
 * Time: 14:01
 * keep informational messages and errors.
 */

namespace Parser\Model;

class SimpleObject
{
    public $_properties = [];
    private $_messages = [];
    private $_groupMessages = [];
    private $_errors = [];
    private $_groupErrors = [];
    /**
     * @var float|string
     */
    private $startTime;

    public static function getArrayFromString($string)
    {
        if (is_array($string)) {
            return array_filter($string);
        }

        $bom = pack('H*', 'EFBBBF');
        $string = preg_replace("/^$bom/", '', $string);

        $string = str_replace(" ", ";", trim($string));
        $string = str_replace(",", ";", $string);
        $string = str_replace("\r\n", ";", $string);
        $string = str_replace("\r", ";", $string);
        $string = str_replace("\n", ";", $string);

        $data = explode(";", $string);
        return array_filter($data);
    }

    public static function remove_utf8_bom($text)
    {
    }

    public function loadFromArray($data = [])
    {
        $this->_properties = $data;
        return $this;
    }

    public function loadErrors($source)
    {
        if (is_object($source)) {
            $source = $source->getErrors();
        }
        if (is_array($source)) {
            foreach ($source as $error) {
                $this->addError($error);
            }
        }
    }

    public function addError($message, $groupKey = '')
    {
        if (is_array($message)) {
            foreach ($message as $item) {
                $this->addError($item, $groupKey);
            }
        } else {
            if ($message) {
                if ($groupKey) {
                    $this->_groupErrors[$groupKey][] = $message;
                } else {
                    $this->_errors[] = $message;
                }
            }
        }
        return $this;
    }

    public function getProperty($name)
    {
        $properties = $this->getProperties();
        return isset($properties[$name]) ? $properties[$name] : null;
    }

    public function getProperties()
    {
        return $this->_properties;
    }

    /**
     * @param $name string
     * @param $value string|array|object
     * @return $this
     */
    public function setProperty($name, $value)
    {
        $this->_properties[$name] = $value;
        return $this;
    }

    public function addMessage($message, $groupKey = '')
    {
        if (!$message) {
            return $this;
        }
        if ($groupKey) {
            $this->_groupMessages[$groupKey][] = $message;
        } else {
            $this->_messages[] = $message;
        }
        return $this;
    }

    public function addExtendedError(\Exception $e)
    {
        $this->addError($e->getFile() . " : " . $e->getLine() . " : " . $e->getMessage());
    }

    public function hasMessages()
    {
        return count($this->_messages) || count($this->_groupMessages);
    }

    public function hasErrors()
    {
        return count($this->_errors) || count($this->_groupErrors);
    }

    public function getStringErrorMessages($separator = ', '): string
    {
        return implode($separator, $this->getErrors());
    }

    public function getErrors(): array
    {
        $errors = $this->_errors;
        if (count($this->_groupErrors)) {
            foreach ($this->_groupErrors as $key => $groupError) {
                $errors[] = $key . " (" . count($groupError) . ") :(" . implode(", ", $groupError) . ")";
            }
        }
        return $errors;
    }

    public function getStringMessages($separator = ', ')
    {
        return implode($separator, $this->getMessages());
    }

    public function getMessages()
    {
        $messages = $this->_messages;
        if (count($this->_groupMessages)) {
            foreach ($this->_groupMessages as $key => $message) {
                $messages[] = $key . " (" . count($message) . "):(" . implode(" ,", $message) . ")";
            }
        }
        return $messages;
    }

    /**
     * @param SimpleObject $object
     * @param bool $clearMessages
     * @return SimpleObject
     */
    public function appendMessagesFromObject(SimpleObject $object, $clearMessages = false): SimpleObject
    {
        $this->_errors = array_merge($this->_errors, $object->getErrors());
        $this->_messages = array_merge($this->_messages, $object->getMessages());
        if ($clearMessages) {
            $this->clearMessages()
                ->clearErrors();
        }
        return $this;
    }

    public function clearErrors(): SimpleObject
    {
        $this->_groupErrors = [];
        $this->_errors = [];
        return $this;
    }

    public function clearMessages(): SimpleObject
    {
        $this->_groupMessages = [];
        $this->_messages = [];
        return $this;
    }

    public function startTimeEvent(): SimpleObject
    {
        // no check if it is already started
        $this->startTime = microtime(true);
        return $this;
    }

    /**
     * time in milliseconds
     * @return int
     */
    public function endTimeEvent(): int
    {
        if (!$this->startTime) {
            return null;
        }
        $timeTaken = (int)(1000 * (microtime(true) - $this->startTime));
        $this->startTime = null;
        return $timeTaken;
    }


}