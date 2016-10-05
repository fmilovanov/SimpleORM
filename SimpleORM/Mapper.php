<?php
/**
  @author Felix A. Milovanov
*/

abstract class Mapper
{
    private static $__instances = [];

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

    public function save(\Model $model)
    {

    }

    public function search(\Model $model)
    {

    }
}
