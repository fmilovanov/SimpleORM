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
        foreach ($this->_simpleMatch($table, $where) as $id)
        {
            unset($this->tables[$table][$id]);
        }
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
        $this->tables_transaction = null;
    }

    private function _match(\DbWhereCond $cond, $value)
    {
        switch ($cond->operator)
        {
            case \DbSelect::OPERATOR_EQ:
                return $cond->val1 === $value;

            case \DbSelect::OPERATOR_NE:
                return $cond->val1 !== $value;

            case \DbSelect::OPERATOR_LT:
                return $cond->val1 > $value;

            case \DbSelect::OPERATOR_LTE:
                return $cond->val1 >= $value;

            case \DbSelect::OPERATOR_GT:
                return $cond->val1 < $value;

            case \DbSelect::OPERATOR_GTE:
                return $cond->val1 <= $value;

            case \DbSelect::OPERATOR_IN:
                if (empty($cond->val1))
                    return is_null($value);
                if (in_array($value, $cond->val1, true))
                    return true;

                return false;

            case \DbSelect::OPERATOR_NOT_IN:
                if (empty($cond->val1))
                    return !is_null($value);
                if (!in_array($value, $cond->val1, true))
                    return true;

                return false;

            case \DbSelect::OPERATOR_BETWEEN:
                return ($value >= $cond->val1) && ($value <= $cond->val2);


            default:
                throw new \Exception($cond->key . ': unknown operator');
        }
    }

    public function query(\DbSelect $select)
    {
        $table = $select->getTable();

        if (!isset($this->tables[$table]))
            throw new \Exception(self::ERROR_NO_TABLE);

        if ($columns = $select->getColumns())
        {
            //
        }
        else $columns = '*';



        $result = array();
        foreach ($this->tables[$table] as $id => $data)
        {
            foreach ($select->getWhere() as $conditions)
            {
                $matches = false;
                foreach ($conditions as $cond)
                {
                    if (!array_key_exists($cond->key, $data))
                        throw new \Exception(self::ERROR_NO_KEY);

                    if ($this->_match($cond, $data[$cond->key]))
                        $matches = true;
                }

                if (!$matches)
                    continue 2;
            }

            $result[] = $data;
        }

        
        // select columns
        if ($columns != '*')
        {
            $temp = [];
            foreach ($result as $data)
            {
                $new_data = [];
                foreach ($data as $key => $value)
                {
                    if (in_array($key, $columns))
                        $new_data[$key] = $value;
                }

                $temp[] = $new_data;
            }

            $result = $temp;
        }


        return $result;
    }

}