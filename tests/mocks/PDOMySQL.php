<?php
/**
 * @author Felix A. Milovanov <fmilovanov@yahoo.com>
 */

class StmtMysql
{
    private $_str;


    public function __construct($str)
    {
        $this->_str = $str;
    }


    public function execute()
    {
    }

    public function fetchAll()
    {
        return [[$this->_str]];
    }
}

class PDOMySQL extends PDO
{
    const ERROR_ATTRIBUTE   = 'Unknown attribute';
    const ERROR_BAD_SQL     = 'Bad SQL';
    const ERROR_TABLE       = 'No table found';

    public $tables = [];

    public function __construct()
    {
    }

    public function getAttribute($attribute)
    {
        if ($attribute != PDO::ATTR_DRIVER_NAME)
            throw new \Exception(self::ERROR_ATTRIBUTE);

        return 'mysql';
    }

    public function prepare($statement, array $driver_options = array())
    {
        if (!preg_match('/^SHOW CREATE TABLE `([^`]+)`$/', $statement, $m))
            throw new \Exception(self::ERROR_BAD_SQL);

        if (!isset($this->tables[$m[1]]))
            throw new \Exception(self::ERROR_TABLE);

        return new StmtMysql($this->tables[$m[1]]);
    }
}
