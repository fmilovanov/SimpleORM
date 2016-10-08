<?php
/**
  @author Felix A. Milovanov
*/

abstract class Mapper
{
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

    public function getModel($data = null)
    {
        $name = get_class($this);
        if (preg_match('/^Mapper_/', $class))
            $class = preg_replace('/^Mapper_/', 'Model_', $class);
        else
            throw new \Exception('Cannot determine mapper name');

        return new $name($data);
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
        foreach ($this->getColumns() as $key => $method)
        {
            $getter = 'get' . $method;
            $setter = 'set' . $method;
            switch ($key)
            {
                case 'created_on':
                    if ($model->getId())
                        break;

                    $model->$setter(self::sqlNow());
                    $data[$key] = $model->$method();
                    break;

                case 'updated_on':
                    $data[$key] = self::sqlNow();
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
        }
    }

    public static function sqlNow()
    {
        return date('Y-m-d H:i:s');
    }

    public function search(\Model $model)
    {

    }

    public function delete(\Model $model, $herd = false)
    {

    }
}
