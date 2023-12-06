<?php
/**
 * Created by WebExperiment.
 * User: dom
 * Date: 11.08.18
 * Time: 0:13
 */

namespace Parser\Model;

use Laminas\Db\Sql\Where;
use Laminas\Db\TableGateway\TableGateway;

class Profile extends TableGateway
{
    public $lastInsertValue;
    public $table;
    public $adapter;
    public $data;
    public $identity;
    public $config;
    private $schema;


    public function __construct($db, $identity)
    {
        $table = 'human';
        $this->identity = $identity;

        parent::__construct($table, $db);
    }

    public function load($identity = null)
    {
        $identity = $identity ? $identity : $this->identity;
        $where = new Where();
        $where->equalTo('login', $identity);
        $rowSet = $this->select($where);
        $this->data = $rowSet->current();
        unset($this->data['password']);

        if ($this->data['data']) {
            $this->config = json_decode($this->data['data']);
        } else {
            $this->config = new \stdClass();
        }
        return $this;
    }

    public function resetData($key)
    {
        $this->config->{$key} = new \stdClass();
        return $this->updateData([], 1);
    }

    public function updateData($data, $force = false)
    {
        $changed = false;
        foreach ($data as $key => $value) {
            if (is_array($value)) {
//                if(! isset($this->config->{$key})) {
//                    $this->config->{$key} = new \stdClass();
//                }
                foreach ($value as $k => $item) {
                    if (! isset($this->config->{$key}->{$k})) {
                        $changed = true;
                        $this->config->{$key}->{$k} = $item;
                    } elseif ($this->config->{$key}->{$k} !== $item) {
                        $changed = true;
                        $this->config->{$key}->{$k} = $item;
                    }
                }
            } elseif (! isset($this->config->{$key})) {
                $changed = true;
                $this->config->{$key} = $value;
            } elseif ($this->config->{$key} !== $value) {
                $changed = true;
                $this->config->{$key} = $value;
            }
        }
        if ($changed || $force) {
            $this->data['data'] = json_encode($this->config);
            $where = new Where();
            $where->equalTo('login', $this->identity);
            $this->update(['data' => $this->data['data']], $where);
        }
    }

    public function loadConfigData($key)
    {
        if (isset($this->config->{$key})) {
            return (array)$this->config->{$key};
        } else {
            return [];
        }
    }

    /**
     * @param array $array
     */
    public function updateProfileSettings(array $array): void
    {
        $profSettings = $this->loadConfigData('profileSettings');
        $profSettings = array_merge($profSettings, $array);
        $this->updateData(['profileSettings' => $profSettings]);
    }

    public function getKeepaApiKey($identity = null) {
        return $this->getProfileSetting('keepaApi', $identity);
    }

    /**
     * @param string $setting
     * @param null $identity
     * @return mixed|null
     */
    public function getProfileSetting($setting, $identity = null) {
        if(!$identity) {
            $identity = 'admin';
        }
        $adminProf = new Profile($this->getAdapter(), $identity);
        $adminProf->load();
        $profileSettings = $adminProf->loadConfigData('profileSettings');
        return $profileSettings[$setting] ?? null;
    }
}