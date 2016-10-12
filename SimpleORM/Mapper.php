<?php
/**
  @author Felix A. Milovanov
*/

abstract class Mapper
{
    private $__cache = array();
    private static $__dbAdapter;
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
        foreach ($this->getColumns() as $key => $method)
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
