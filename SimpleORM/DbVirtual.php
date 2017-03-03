<?php
/*
 * @author Felix A. Milovanov
 */

class DbVirtual implements IDbAdapter
{
    const TYPE_INT      = 'int';
    const TYPE_CHAR     = 'char';
    const TYPE_ENUM     = 'enum';
    const TYPE_DATE     = 'date';
    const TYPE_FLOAT    = 'float';
    const TYPE_DATETIME = 'datetime';
    const TYPE_SET      = 'set';

    const ERROR_NO_TABLE        = 'no table found';
    const ERROR_NO_KEY          = 'no key found';
    const ERROR_TRANSACTION     = 'already in transaction';
    const ERROR_NO_TRANSACTION  = 'no transaction started';

    const ERROR_VALUE_NULL          = '`%s` can not be null';
    const ERROR_VALUE_NOT_SCALAR    = '`%s` is not a scalar';
    const ERROR_VALUE_NO_DEFAULT    = '`%s` does not have default value';
    const ERROR_VALUE_INT           = '`%s` is not an int';
    const ERROR_VALUE_CHAR          = '`%s` is too long';
    const ERROR_VALUE_FLOAT         = '`%s` is not an float';
    const ERROR_VALUE_ENUM          = '`%s` has invalid value';
    const ERROR_VALUE_DATE          = '`%s` is not a valid date';
    const ERROR_VALUE_DATETIME      = '`%s` is not a valid datetime';

    const ERROR_NO_REF_TABLE        = '`%s`: no ref table found';
    const ERROR_FOREIGN_KEY_CHILD   = '#1452 - Cannot add or update a child row: a foreign key constraint fails (`%s`, CONSTRAINT `%s` FOREIGN KEY (`%s`) REFERENCES `%s` (`%s`)) ';
    const ERROR_FOREIGN_KEY_PARENT  = '#1451 - Cannot delete or update a parent row: a foreign key constraint fails (`%s`, CONSTRAINT `%s` FOREIGN KEY (`%s`) REFERENCES `%s` (`%s`)) ';

    const DEFAULT_CTS           = 'CURRENT_TIMESTAMP';

    private $_pdo;
    private $_order;

    public $last_id;
    public $tables = array();
    private $_table_def = [];
    public $tables_transaction;

    public function __construct(PDO $pdo = null)
    {
        $this->_pdo = $pdo;
        $this->last_id = rand(100, 299);
    }

    private function _throw()
    {
        $str = call_user_func_array('sprintf', func_get_args());

        throw new Exception($str);
    }

    private function _throwPdo()
    {
        throw new \PDOException(call_user_func_array('sprintf', func_get_args()));
    }

    private function _validate($table, array &$data, $populate= false)
    {
        if (!array_key_exists($table, $this->_table_def))
            return true;

        $table_def = $this->_table_def[$table];
        foreach ($data as $key => $value)
        {
            if (!isset($table_def[$key]))
                throw new \Exception("Unknown column '$key' in 'field list");

            $field = $table_def[$key];

            if (is_null($value))
            {
                if (!$field->null)
                {
                    if (!$populate)
                        $this->_throw(self::ERROR_VALUE_NULL, $key);

                    if (!isset($field->default))
                        $this->_throw(self::ERROR_VALUE_NO_DEFAULT, $key);

                    $data[$key] = $field->default;
                }

                continue;
            }


            if (!is_scalar($value))
                $this->_throw(self::ERROR_VALUE_NOT_SCALAR, $key);

            switch ($table_def[$key]->type)
            {
                case self::TYPE_INT:
                    if (!is_int($value) && !preg_match('/^[-+]?\d+$/', $value))
                        $this->_throw(self::ERROR_VALUE_INT, $key);
                    break;

                case self::TYPE_CHAR:
                    if (strlen($value) > $field->len)
                        $this->_throw(self::ERROR_VALUE_CHAR, $key);
                    break;

                case self::TYPE_FLOAT:
                    if (!is_float($value) && !is_numeric($value))
                        $this->_throw(self::ERROR_VALUE_FLOAT, $key);
                    break;

                case self::TYPE_ENUM:
                    if (!in_array($value, $table_def[$key]->values, true))
                        $this->_throw(self::ERROR_VALUE_ENUM, $key);
                    break;

                case self::TYPE_DATE:
                    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value))
                        $this->_throw(self::ERROR_VALUE_DATE, $key);
                    break;

                case self::TYPE_DATETIME:
                    if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value))
                        $this->_throw(self::ERROR_VALUE_DATETIME, $key);
                    break;

                default:
                    break;
            }
        }

        if ($populate)
        {
            foreach ($table_def as $key => $column)
            {
                if (array_key_exists($key, $data))
                    continue;

                if (!property_exists($column, 'default'))
                {
                    if (!$column->null)
                        $this->_throw(self::ERROR_VALUE_NO_DEFAULT, $key);

                    $data[$key] = NULL;
                }
                else  $data[$key] = $column->default;
            }
        }

        // check foreign keys
        foreach ($data as $key => $value)
        {
            $field = $table_def[$key];

            if (isset($field->fk))
            {
                foreach ($field->fk as $ref => $def)
                {
                    if (!$this->_simpleMatch($def[0], [$def[1] => $value]))
                        $this->_throwPdo(self::ERROR_FOREIGN_KEY_CHILD, $table, $ref, $key, $def[0], $def[1]);
                }
            }

            if (isset($field->ref))
            {
                
            }
        }
    }

    public function insert($table, array $data)
    {
        if (!array_key_exists($table, $this->tables))
        {
            $this->tables[$table] = array();
        }

        $this->_validate($table, $data, true);



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
        $this->_validate($table, $data);

        $ids = $this->_simpleMatch($table, $where);

        // check if there are references
        if (isset($this->_table_def[$table]))
        {
            $table_def = $this->_table_def[$table];

            foreach ($ids as $id)
            {
                foreach ($data as $key => $value)
                {
                    if (!property_exists($table_def[$key], 'ref'))
                        continue;

                    if ($value == $this->tables[$table][$id][$key])
                        continue;

                    foreach ($table_def[$key]->ref as $ref => $def)
                    {
                        $refs = $this->_simpleMatch($def[0], [$def[1] => $this->tables[$table][$id][$key]]);
                        if ($this->_simpleMatch($def[0], [$def[1] => $this->tables[$table][$id][$key]]))
                            $this->_throwPdo(self::ERROR_FOREIGN_KEY_PARENT, $table, $ref, $key, $def[0], $def[1]);
                    }
                }
            }
        }

        foreach ($ids as $id)
        {
            foreach ($data as $key => $value)
            {
                $this->tables[$table][$id][$key] = $value;
            }
        }
    }

    private function _findReferences($table, $data)
    {
        if (!isset($this->_table_def[$table]))
            return false;

        $table_def = $this->_table_def[$table];
        foreach ($data as $key => $value)
        {
            if (!isset($table_def[$key]))
                continue;

            if (!property_exists($table_def[$key], 'ref'))
                continue;

            foreach ($table_def[$key]->ref as $ref => $def)
            {
                if ($this->_simpleMatch($def[0], [$def[1] => $value]))
                    $this->_throwPdo(self::ERROR_FOREIGN_KEY_PARENT, $table, $ref, $key, $def[0], $def[1]);
            }
        }

        return false;

    }

    public function delete($table, array $where, $allow_delete_all = false)
    {
        $ids = $this->_simpleMatch($table, $where);
        foreach ($ids as $id)
        {
            $this->_findReferences($table, $this->tables[$table][$id]);
        }

        foreach ($ids as $id)
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

    private function _initMysql($name)
    {
        $stmt = $this->_pdo->prepare("SHOW CREATE TABLE `$name`");
        $stmt->execute();

        $data = explode("\n", array_pop(array_pop($stmt->fetchAll(\PDO::FETCH_ASSOC))));
        if (!preg_match('/^CREATE TABLE /', array_shift($data)))
            throw new Exception('Bad create table');

        $table_def = [];
        foreach ($data as $string)
        {
            // column definition
            if (preg_match('/^\s+`([^`]+)`\s+([^ ]+)\s+(.*)$/', $string, $m))
            {
                $column = new stdClass();
                $column->null = !preg_match('/NOT NULL/', $m[3]);
                if (preg_match("/DEFAULT '(.*)'/", $m[3], $n))
                    $column->default = str_replace("''", "'", $n[1]);

                if (preg_match('/^(|tiny|small|medium|big)int[(](\d+)[)]/', $m[2], $n))
                {
                    $column->type = self::TYPE_INT;
                    $column->len = $n[2];
                }
                elseif (preg_match('/^(|var)char[(](\d+)[)]/', $m[2], $n))
                {
                    $column->type = self::TYPE_CHAR;
                    $column->len = $n[2];
                }
                elseif (preg_match('/(text|blob)/', $m[2], $n))
                {
                    $column->type = self::TYPE_CHAR;
                    $column->len = 65536;
                }
                elseif (preg_match('/(decimal|float|double)/', $m[2]))
                {
                    $column->type = self::TYPE_FLOAT;
                }
                elseif (preg_match('/^(datetime|timestamp)/', $m[2]))
                {
                    $column->type = self::TYPE_DATETIME;
                    if (preg_match('/DEFAULT CURRENT_TIMESTAMP/', $m[3]))
                        $column->default = self::DEFAULT_CTS;
                    if (preg_match('/ON UPDATE CURRENT_TIMESTAMP/', $m[3]))
                        $column->on_update = self::DEFAULT_CTS;

                }
                elseif (preg_match('/^date/', $m[2]))
                {
                    $column->type = self::TYPE_DATE;
                }
                elseif (preg_match('/^enum[(](.*)[)]/', $m[2], $n))
                {
                    $column->type = self::TYPE_ENUM;
                    $column->values = explode(',', $n[1]);
                    foreach ($column->values as $id => $val)
                        $column->values[$id] = preg_replace('/\'$/', '', preg_replace ('/^\'/', '', $val));
                }
                else throw new \Exception('`' . $m[1] . '`: unsuppoted field type');




                $table_def[$m[1]] = $column;
                //print_r($m);
            }

            if (preg_match('/CONSTRAINT `([^`]+)` FOREIGN KEY \(`([^`]+)`\) REFERENCES `([^`]+)` \(`([^`]+)`\)/', $string, $m))
            {
                $ref = $m[1];
                $field = $m[2];
                $table = $m[3];
                $ref_field = $m[4];
                if (!array_key_exists($field, $table_def))
                    throw new \Exception("`$ref`: no field found");

                if (!array_key_exists($table, $this->_table_def))
                    $this->_throw(self::ERROR_NO_REF_TABLE, $ref);

                if (!array_key_exists($ref_field, $this->_table_def[$table]))
                    throw new \Exception("`$ref`: no ref field found");

                if (!isset($table_def[$field]->fk))
                    $table_def[$field]->fk = [];

                if (!isset($this->_table_def[$table][$ref_field]->ref))
                    $this->_table_def[$table][$ref_field]->ref = [];

                $table_def[$field]->fk[$ref] = [$table, $ref_field];
                $this->_table_def[$table][$ref_field]->ref[$ref] = [$name, $field];
            }
        }

        $this->_table_def[$name] = $table_def;


//        print_r($this->_table_def);
        
    }

    public function getTableDef($name)
    {
        if (!isset($this->_table_def[$name]))
            throw new Exception('No table found');

        return $this->_table_def[$name];
    }

    public function createTable($name)
    {
        if (isset($this->tables[$name]))
            return;

        
        if (!$this->_pdo)
        {
            $this->tables[$name] = [];
            return;
        }

        switch ($this->_pdo->getAttribute(\PDO::ATTR_DRIVER_NAME))
        {
            case 'mysql':
                $this->_initMysql($name);
                break;

            default:
                $x = 1;
        }

        $this->tables[$name] = [];
    }
}