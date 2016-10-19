<?php
/*
 * @author Felix A. Milovanov
 */

class DbVirtual implements IDbAdapter
{
    private $_pdo;

    public $last_id;
    public $tables = array();

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

    public function update($table, array $data, array $where)
    {

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

    }

    public function commit()
    {

    }

    public function rollBack()
    {

    }

    public function query(\DbSelect $select)
    {

    }

}