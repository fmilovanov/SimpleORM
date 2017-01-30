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
    private $_order;

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

    public function _compare($a, $b)
    {
        foreach ($this->_order as $key => $direction)
        {
            if (!array_key_exists($key, $a) || !array_key_exists($key, $b))
                throw new Exception('Unknown sorting key: ' . $key);

            if ($a[$key] == $b[$key])
                continue;

            if (is_int($a[$key]) || is_float($a[$key]))
                $res = $a[$key] > $b[$key] ? 1 : -1;
            else
                $res = strcmp($a[$key], $b[$key]);

            return ($direction == 'ASC') ? $res : -1 * $res;
        }

        return 0;
    }

    private function _join(array $jresult, $alias, \DbJoin $join)
    {
        $result = [];
        foreach ($jresult as $row)
        {
            $empty_join = false;
            $select = new \DbSelect($join->table);
            foreach ($join->on as $key => $value)
            {
                if (is_array($value))
                {
                    if (is_array($row[$value[0]]))
                    {
                        $where = $row[$value[0]][$value[1]];
                        $select->where($key, $where ? $where : \DbSelect::is_null());
                    }
                    else $empty_join = true;
                }
                elseif (is_object($value) || is_scalar($value))
                {
                    $select->where($key, $value);
                }
            }

            // join those rows
            $count = 0;
            if (!$empty_join)
            {
                foreach ($this->query($select) as $jrow)
                {
                    $row[$alias] = $jrow;
                    $result[] = $row;
                    $count += 1;
                }
            }

            if (!$count && ($join->type == \DbJoin::TYPE_LEFT))
            {
                $row[$alias] = true;
                $result[] = $row;
            }
        }

        return $result;
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

        // main select
        $jresult = array();
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

            $jresult[] = [$table => $data];
        }

        // process joins
        $join_columns = [];
        foreach ($select->getJoins() as $jalias => $join)
        {
            $jresult = $this->_join($jresult, $jalias, $join);
            if ($join->columns)
                $join_columns[$jalias] = $join->columns;
        }

        // denormalize
        $result = [];
        foreach ($jresult as $data)
        {
            // denormalize table
            if (is_array($columns))
            {
                $row = [];
                foreach ($data[$table] as $key => $value)
                {
                    if (in_array($key, $columns))
                        $row[$key] = $value;
                }
            }
            else $row = $data[$table];

            // denormalize joins
            foreach ($join_columns as $jalias => $jcolumns)
            {
                if (is_array($jcolumns))
                {
                    foreach ($jcolumns as $key => $value)
                    {
                        if (is_array($data[$jalias]))
                            $row[$value] = $data[$jalias][is_numeric($key) ? $value : $key];
                        else
                            $row[$value] = null;
                    }
                }
                else
                {
                    foreach ($jresult[$jalias] as $key => $value)
                        $row[$key] = $value;
                }
            }


            $result[] = $row;
        }
        
        // process order
        if ($select->getOrder())
        {
            $this->_order = $select->getOrder();
            usort($result, array($this, '_compare'));
        }

        if ($select->getSearchLimit())
        {
            list($limit, $offset) = $select->getSearchLimit();
            $result = $offset ? array_slice($result, $offset, $limit) : array_slice($result, 0, $limit);
        }


        return $result;
    }

}