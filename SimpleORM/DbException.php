<?php
/*
 * @author Felix A. Milovanov
 */


class DbException extends Exception
{
    private $_sql;
    private $_params;

    public function getSQL() { return $this->_sql; }
    public function setSQL($val)
    {
        $this->_sql = $val;
        return $this;
    }

    public function getParams() { return $this->_params; }
    public function setParams($val)
    {
        $this->_params = $val;
        return $this;
    }

}