<?php
/*
 * @author Felix A. Milovanov
 */

class DbVirtual implements IDbAdapter
{
    const ERROR_NO_TABLE        = 'no table found';
    const ERROR_NO_KEY          = 'no key found';
    const ERROR_TRANSACTION     = 'already in transaction';
    const ERROR_NO_TRANSACTION  = 'no transaction started';

    private $_pdo;

    public $last_id;
    public $tables = array();
    public $tables_transaction;

    public function __construct(PDO $pdo = null)
    {
        $this->_pdo = $pdo;
        $this->last_id = rand(100, 299);
    }

    public function insert($table, array $data)
    {
        if (!array_key_exists($table, $this->tables))
        {
            $this->tables[$table] = array();
        }

        $this->last_id += 1;
        $data['id'] = $this->last_id;
        $this->tables[$table][$this->last_id] = $data;
    }

    private function _simpleMatch($table, array $where)
    {
        if (!isset($this->tables[$table]))
            throw new \Exception(self::ERROR_NO_TABLE);

        $ids = array();
        foreach ($this->tables[$table] as $id => $data)
        {
            foreach ($where as $key => $value)
            {
                if (!array_key_exists($key, $data))
                    throw new \Excpetion(self::ERROR_NO_KEY);

                if ($value !== $data[$key])
                    continue 2;
            }

            $ids[] = $id;
        }

        return $ids;
    }

    public function update($table, array $data, array $where)
    {
        foreach ($this->_simpleMatch($table, $where) as $id)
        {
            foreach ($data as $key => $value)
            {
                $this->tables[$table][$id][$key] = $value;
            }
        }
    }

    public function delete($table, array $where, $allow_delete_all = false)
    {

    }

    public function lastInsertId()
    {
        return $this->last_id;
    }

    public function beginTransaction()
    {
        if (!is_null($this->tables_transaction))
            throw new \Exception(self::ERROR_TRANSACTION);

        $this->tables_transaction = $this->tables;
    }

    public function commit()
    {
        if (is_null($this->tables_transaction))
            throw new \Exception(self::ERROR_NO_TRANSACTION);

        $this->tables_transaction = null;
    }

    public function rollBack()
    {
        if (is_null($this->tables_transaction))
            throw new \Exception(self::ERROR_NO_TRANSACTION);

        $this->tables = $this->tables_transaction;
    }

    public function query(\DbSelect $select)
    {

    }

}