<?php
/**
  @author Felix A. Milovanov
*/

abstract class Mapper
{
    const ERROR_NO_ID           = 'No ID in this model';
    const ERROR_SOFT_DELETE     = 'This object does not support soft delete';
    const ERROR_JOIN            = 'Incorrect join - cannot load object back';
    const ERROR_JOIN_COLUMN     = 'Join column setter not found';

    private $__cache = array();
    private static $__dbAdapter;
    private static $__transaction_id;
    protected static $__instances = array();

    private function __construct() { }

    abstract public function getColumns();

    /**
     * @return Mapper
     */
    public static function getInstance()
    {
        $cname = get_called_class();
        if (!isset(self::$__instances[$cname]))
        {
            static::$__instances[$cname] = new $cname();
        }

        return static::$__instances[$cname];
    }

    public static function setDefaultDbAdapter(IDbAdapter $adapter)
    {
        self::$__dbAdapter = $adapter;
    }

    /**
     * @return IDbAdapter
     * @throws Exception
     */
    public static function getDbAdapter()
    {
        if (self::$__dbAdapter === null)
            throw new Exception('No DB adapter defined');

        return self::$__dbAdapter;
    }

    /**
     * @return Model
     * @throws \Exception
     */
    public function getModel($data = null)
    {
        if (is_array($data) && isset($data['id']) && isset($this->__cache[$data['id']]))
            return $this->__cache[$data['id']];

        $class = get_class($this);
        if (preg_match('/^Mapper_/', $class))
            $class = preg_replace('/^Mapper_/', 'Model_', $class);
        else
            throw new \Exception('Cannot determine mapper name');

        $this->__cache[$data['id']] = new $class($data);
        return $this->__cache[$data['id']];
    }

    public function getTableName()
    {
        $name = get_class($this);
        if (preg_match('/^Mapper_/', $name))
        {
            $name = preg_replace('/^Mapper_/', '', $name);
        }
        else
        {
            throw new Exception('Cannot determine table name');
        }

        return strtolower(preg_replace('/([A-Z])/', '_$1', lcfirst($name)));
    }

    public function save(\Model $model)
    {
        if (method_exists($model, 'validate'))
            $model->validate();

        $data = array();
        $created_on = $updated_on = false;
        $columns = $this->getColumns();
        foreach ($columns as $key => $method)
        {
            $getter = 'get' . $method;
            $setter = 'set' . $method;
            switch ($key)
            {
                case 'created_on':
                    if ($model->getId())
                        break;

                    $data[$key] = self::sqlNow();
                    $created_on = true;
                    break;

                case 'updated_on':
                    $data[$key] = self::sqlNow();
                    $updated_on = true;
                    break;

                default:
                    $data[$key] = $model->$getter();
            }
        }

        if ($model->getId())
        {
            self::getDbAdapter()->update($this->getTableName(), $data, array('id' => $model->getId()));
        }
        else
        {
            self::getDbAdapter()->insert($this->getTableName(), $data);
            $model->setId(self::getDbAdapter()->lastInsertId());
            if ($created_on)
                $model->setCreatedOn($data['created_on']);
        }
        if ($updated_on)
            $model->setUpdatedOn($data['updated_on']);

        if (method_exists($this, 'addJoins'))
        {
            // make a request
            $select = new \DbSelect($this->getTableName(), array());
            $select->where('id', $model->getId());
            $this->addJoins($select);

            $data = $this->getDbAdapter()->query($select);
            if (count($data) != 1)
                throw new Exception(self::ERROR_JOIN);

            foreach (array_pop($data) as $key => $value)
            {
                $setter = 'set' . str_replace(' ', '', ucwords(str_replace('_', ' ', $key)));
                if (!method_exists($model, $setter))
                    throw new Exception(self::ERROR_JOIN_COLUMN);
                $model->$setter($value);
            }
        }

        $this->__cache[$model->getId()] = $model;
    }

    public static function sqlNow()
    {
        return date('Y-m-d H:i:s');
    }

    public function search(\Model $model, $order = null)
    {
        $select = new \DbSelect($this->getTableName());
        $columns = $this->getColumns();
        $columns['id'] = 'Id';
        foreach ($columns as $key => $getter)
        {
            $getter = "get$getter";
            $value = $model->$getter();

            if (!is_null($value))
                $select->where($key, $value);
        }

        $select->setOrder($order);

        $result = array();
        foreach ($this->getDbAdapter()->query($select) as $data)
            $result[] = $this->getModel($data);

        return $result;
    }

    public function find($id)
    {
        if (isset($this->__cache[$id]))
            return $this->__cache[$id];

        $model = $this->getModel(false);
        $model->setId($id);

        return array_pop($model->search());
    }

    public function fetchAll($sort = null)
    {
        $model = $this->getModel(false);

        return $model->search($order);
    }

    public function delete(\Model $model, $hard = false)
    {
        if (!$model->getId())
            throw new \Exception(self::ERROR_NO_ID);

        if ($hard)
        {
            $this->getDbAdapter()->delete($this->getTableName(), array('id' => $model->getId()));
        }
        else
        {
            if (!array_key_exists('deleted_on', $this->getColumns()))
                throw new \Exception(self::ERROR_SOFT_DELETE);

            $now = self::sqlNow();
            $this->getDbAdapter()->update($this->getTableName(), array('deleted_on' => $now),
                                          array('id' => $model->getId()));

            $model->setDeletedOn($now);
        }

        if (isset($this->__cache[$model->getId()]))
            unset($this->__cache[$model->getId()]);
    }

    public function beginTransaction()
    {
        if (!is_null(self::$__transaction_id))
            return null;

        self::$__transaction_id = md5(microtime(true) . rand(10000, 99999));
        $this->getDbAdapter()->beginTransaction();
        return self::$__transaction_id;
    }

    public function commit($tid)
    {
        if ($tid != self::$__transaction_id)
            return;

        $this->getDbAdapter()->commit();
        self::$__transaction_id = null;
    }

    public function rollback($tid)
    {
        if ($tid != self::$__transaction_id)
            return;

        $this->getDbAdapter()->rollBack();
        self::$__transaction_id = null;
    }



    public function clearCache()
    {
        $this->__cache = array();
    }

    public static function clearAllCaches()
    {
        foreach (self::$__instances as $mapper)
            $mapper->clearCache();
    }
}
