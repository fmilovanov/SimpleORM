<?php
/*
 * @author Felix A. Milovanov
 */

class DbSql implements IDbAdapter
{
    private $_adapter;


    public function __construct(PDO $db)
    {
        $this->_adapter = $db;
    }

    private function _prepareSetClause(array $data, array &$params)
    {
        $i = 0;
        $SQLStr = '';
        foreach ($data as $key => $value)
        {
            if (!is_null($value) && !is_scalar($value))
                throw new Exception($key . ' is not scalar');

            $SQLStr .= ', `' . $key . "` = :c$i";
            $params[":c$i"] = $value;
            $i += 1;
        }

        return $SQLStr ? substr($SQLStr, 1) : '';
    }

    /**
     * @return PDO
     */
    protected function getAdapter()
    {
        return $this->_adapter;
    }

    public function insert($table, array $data)
    {
        ;
    }

    public function update($table, array $data, array $where) {
        ;
    }

    public function lastInsertId() {
        ;
    }

    public function beginTransaction()
    {
        $this->getAdapter()->beginTransaction();
    }

    public function commit()
    {
        $this->getAdapter()->commit();
    }

    public function rollBack()
    {
        $this->getAdapter()->rollBack();
    }

}