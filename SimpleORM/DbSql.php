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

    private function _throw($object, $sql = null, $params = null)
    {
        $error = $object->errorInfo();
        $e = new \DbException($error[2], $error[1]);
        $e->setSQL($sql);
        $e->setParams($params);

        throw $e;
    }

    private function _prepareSetClause(array $data, array &$params)
    {
        $i = 0;
        $SQLStr = '';
        foreach ($data as $key => $value)
        {
            if (!is_null($value) && !is_scalar($value))
                throw new \Exception($key . ' is not scalar');

            $SQLStr .= ', `' . $key . "` = :c$i";
            $params[":c$i"] = $value;
            $i += 1;
        }

        return $SQLStr ? substr($SQLStr, 2) : '';
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
        if (empty($data))
            throw new Exception('No data');

        $params = array();
        $SQLStr = 'INSERT INTO `' . $table . '` SET ' . $this->_prepareSetClause($data, $params);

        $stmt = $this->getAdapter()->prepare($SQLStr);
        if (!$stmt->execute($params))
            $this->_throw($stmt, $SQLStr, $params);
    }

    public function update($table, array $data, array $where)
    {
        if (empty($data))
            throw new \Exception('No data');

        if (empty($where))
            throw new \Exception('No where clause');

        $params = array();
        $SQLStr = "UPDATE `$table` SET " . $this->_prepareSetClause($data, $params) . ' WHERE';

        $i = 0;
        foreach ($where as $key => $value)
        {
            if (!is_scalar($value))
                throw new \Exception($key . ' in where is not scalar');

            $SQLStr .= " `$key` = :w$i AND";
            $params[":w$i"] = $value;
            $i += 1;
        }

        $SQLStr = substr($SQLStr, 0, strlen($SQLStr) - 4);
        $stmt = $this->getAdapter()->prepare($SQLStr);
        if (!$stmt->execute($params))
            $this->_throw($stmt, $SQLStr, $params);
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