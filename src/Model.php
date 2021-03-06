<?php
namespace Phwoolcon;

use Exception;
use Phalcon\Db as PhalconDb;
use Phalcon\Mvc\Model as PhalconModel;
use Phalcon\Mvc\ModelInterface;

abstract class Model extends PhalconModel
{
    protected $_data = [];
    protected $pk = 'id';

    public function __get($property)
    {
        return isset($this->_data[$property]) ? $this->_data[$property] : parent::__get($property);
    }

    public function __isset($property)
    {
        return isset($this->_data[$property]) && parent::__isset($property);
    }

    public function __set($property, $value)
    {
        if ($value instanceof ModelInterface) {
            parent::__set($property, $value);
            return $value;
        }
        return $this->_data[$property] = $value;
    }

    public function addData(array $data)
    {
        $this->_data = array_merge($this->_data, $data);
        return $this;
    }

    public function getData($key = null)
    {
        return $key === null ? $this->_data : fnGet($this->_data, $key);
    }

    public function getId()
    {
        return $this->getData($this->pk);
    }

    public function setData($key, $value = null)
    {
        is_array($key) ? $this->_data = $key : $this->_data[$key] = $value;
        return $this;
    }

    public function setId($id)
    {
        return $this->setData($this->pk, $id);
    }

    /**
     * @param      $sql
     * @param null $bind
     * @return bool
     */
    public function sqlExecute($sql, $bind = null)
    {
        $sql = $this->translatePhalconBindIntoPDO($sql, $bind);
        return $this->getReadConnection()->execute($sql, $bind);
    }

    /**
     * @param      $sql
     * @param null $bind
     * @return array
     */
    public function sqlFetchAll($sql, $bind = null)
    {
        $sql = $this->translatePhalconBindIntoPDO($sql, $bind);
        return $this->getReadConnection()->fetchAll($sql, PhalconDb::FETCH_ASSOC, $bind);
    }

    /**
     * @param      $sql
     * @param null $bind
     * @return array
     */
    public function sqlFetchOne($sql, $bind = null)
    {
        $sql = $this->translatePhalconBindIntoPDO($sql, $bind);
        return $this->getReadConnection()->fetchOne($sql, PhalconDb::FETCH_ASSOC, $bind);
    }

    /**
     * @param      $sql
     * @param null $bind
     * @return mixed
     */
    public function sqlFetchColumn($sql, $bind = null)
    {
        $row = $this->sqlFetchOne($sql, $bind);
        return reset($row);
    }

    protected function translatePhalconBindIntoPDO($sql, &$bind = null)
    {
        if (is_array($bind)) {
            foreach ($bind as $key => $val) {
                $replace = [":{$key}:" => ":{$key}"];
                if (strstr($sql, ($from = "{{$key}:array}")) !== false) {
                    if (is_array($val)) {
                        $to = [];
                        foreach (array_values($val) as $vKey => $realVal) {
                            $bind[$to[] = ":{$key}_{$vKey}"] = $realVal;
                        }
                        $replace[$from] = implode(', ', $to);
                        unset($bind[$key]);
                    }
                }
                $sql = strtr($sql, $replace);
            }
        }
        return $sql;
    }

    /**
     * @param array | string | int $conditions
     * @param array                $bind
     * @return $this
     */
    public static function findFirstSimple($conditions, $bind = [])
    {
        $params = static::buildParams($conditions, $bind);
        return static::findFirst($params);
    }

    /**
     * @param       $conditions
     * @param array $bind
     * @return $this
     */
    public static function findSimple($conditions = [], $bind = [])
    {
        $params = static::buildParams($conditions, $bind);
        return static::find($params);
    }

    /**
     * @param array $conditions
     * @param array $bind
     * @return mixed
     */
    public static function countSimple($conditions = [], $bind = [])
    {
        $params = static::buildParams($conditions, $bind);
        return static::count($params);
    }

    /**
     * @param       $conditions
     * @param array $bind
     * @return array
     */
    public static function buildParams($conditions = [], $bind = [])
    {
        $params = [];
        if (empty($conditions)) {
            return $params;
        }
        if (empty($bind)) {
            if (is_array($conditions)) {
                $params['conditions'] = "";
                $params['bind'] = [];
                foreach ($conditions as $key => $value) {
                    if (!is_array($value)) {
                        $operator = '=';
                        $realValue = $value;
                    } else {
                        $operator = reset($value);
                        $realValue = next($value);
                    }
                    $params['conditions'] .= ($params['conditions'] == "" ? "" : " AND ") .
                        " {$key} {$operator} :{$key}: ";
                    $params['bind'][$key] = $realValue;
                }
            } else {
                $params['conditions'] = $conditions;
            }
        } else {
            $params['conditions'] = $conditions;
            $params['bind'] = $bind;
        }
        return $params;
    }

}
