<?php
/**
 * @author Felix A. Milovanov <fmilovanov@yahoo.com>
 */

class TestSTMT
{
    public $sql;
    public $params;
    public $error;
    public $fetch_all;

    public function __construct($sql, $error = null)
    {
        $this->sql = $sql;
        $this->error = $error;
    }

    public function execute($params = array())
    {
        $this->params = $params;
        return $this->error ? false : true;
    }

    public function errorCode()
    {
        return $this->error ? $this->error[0] : null;
    }

    public function errorInfo()
    {
        return $this->error;
    }

    public function fetchAll($type)
    {
        $this->fetch_all = $type;
    }
}

