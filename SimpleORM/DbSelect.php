<?php
/*
 * @author Felix A. Milovanov
 */

class DbWhereCond
{
    public $key;
    public $operator;
    public $val1;
    public $val2;
}

class DbSelect
{
    const OPERATOR_EQ       = 'eq';
    const OPERATOR_LT       = 'lt';
    const OPERATOR_GT       = 'gt';
    const OPERATOR_LTE      = 'lte';
    const OPERATOR_GTE      = 'gte';
    const OPERATOR_IN       = 'in';


    const ERROR_TABLE       = 'bad table name';
    const ERROR_NOT_SCALAR  = 'not a scalar';
    const ERROR_WHERE       = 'unknown where';
    const ERROR_WHERE_OR    = 'no where to add to or';

    private $_table;
    private $_where = array();

    public function __construct($table)
    {
        if (!is_string($table))
            throw new Exception(self::ERROR_TABLE);

        $this->_table = $table;
    }

    public function getTable()
    {
        return $this->_table;
    }

    private function _where($key, $value)
    {
        $cond = new DbWhereCond();
        $cond->key = $key;
        $cond->val1 = $value;

        if (is_scalar($value))
        {
            $cond->operator = self::OPERATOR_EQ;
        }
        elseif (is_array($value))
        {
            foreach ($value as $val)
            {
                if (!is_null($val) && !is_scalar($val))
                    throw new \Exception(self::ERROR_NOT_SCALAR);
            }
            $cond->operator = self::OPERATOR_IN;
        }
        elseif ($value instanceof DbWhereCond)
        {
            $cond = $value;
            $cond->key = $key;
        }
        else
        {
            throw new \Exception();
        }

        return $cond;
    }

    public function where($key, $value)
    {
        $this->_where[] = array($this->_where($key, $value));
    }

    public function whereOr($key, $value)
    {
        $index = count($this->_where) - 1;
        if ($index < 0)
            throw new \Exception();

        $this->_where[$index][] = $this->_where($key, $value);
    }

    public function getWhere()
    {
        return $this->_where;
    }
}