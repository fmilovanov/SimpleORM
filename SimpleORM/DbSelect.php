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
    const OPERATOR_NE       = 'ne';
    const OPERATOR_LT       = 'lt';
    const OPERATOR_GT       = 'gt';
    const OPERATOR_LTE      = 'lte';
    const OPERATOR_GTE      = 'gte';
    const OPERATOR_LIKE     = 'like';
    const OPERATOR_IN       = 'in';
    const OPERATOR_NOT_IN   = 'not_in';
    const OPERATOR_BETWEEN  = 'between';


    const ERROR_TABLE       = 'bad table name';
    const ERROR_NOT_SCALAR  = 'not a scalar';
    const ERROR_WHERE       = 'unknown where';
    const ERROR_WHERE_OR    = 'no where to add to or';
    const ERROR_ORDER       = 'bad order';
    const ERROR_EMPTY       = 'empty columns';

    private $_table;
    private $_columns;
    private $_where = array();
    private $_order;
    private $_search_limit;

    public function __construct($table, array $columns = null)
    {
        if (!is_string($table) || !preg_match('/^[a-z]/i', $table))
            throw new Exception(self::ERROR_TABLE);

        if (is_array($columns))
        {
            if (empty($columns))
                throw new \Exception(self::ERROR_EMPTY);

            foreach ($columns as $field)
            {
                if (!is_string($field) || !$field || !preg_match('/^[a-z]/i', $field))
                    throw new \Exception(self::ERROR_NOT_SCALAR);
            }

            $this->_columns = $columns;
        }

        $this->_table = $table;
    }

    public function getTable()
    {
        return $this->_table;
    }

    public function getColumns()
    {
        return $this->_columns;
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
            throw new \Exception(self::ERROR_WHERE_OR);

        $this->_where[$index][] = $this->_where($key, $value);
    }

    public function getWhere()
    {
        return $this->_where;
    }

    public function getOrder() { return $this->_order; }
    public function setOrder($val)
    {
        if (is_string($val))
        {
            $order = array();
            foreach (preg_split('/\s*,\s*/', $val) as $val)
            {
                if (!preg_match('/^([a-z_][a-z0-9_]+)( +(asc|desc))?$/i', $val, $m) &&
                    !preg_match('/^`([a-z_][a-z0-9_]+)`( +(asc|desc))?$/i', $val, $m))
                    throw new \Exception(self::ERROR_ORDER);

                $order[] = '`' . $m[1] . '`' . (isset($m[3]) ? (' ' . $m[3]) : '');
            }

            $val = implode(', ', $order);
        }
        elseif (!is_null($val))
        {
            throw new \Exception(self::ERROR_ORDER);
        }
        $this->_order = $val;
        return $this;
    }

    public function getSearchLimit() { return $this->_search_limit; }
    public function setSearchLimit($limit, $offset = null)
    {
        $this->_search_limit = array($limit, $offset);
    }

    /**
     *
     * @param string $operator
     * @param string|array $value
     * @param string $value2
     * @return \DbWhereCond
     */
    public static function cond($operator, $value, $value2 = null)
    {
        $cond = new \DbWhereCond();
        $cond->operator = $operator;
        $cond->val1 = $value;
        $cond->val2 = $value2;

        return $cond;
    }

    /**
     * @param string $value
     * @return \DbWhereCond
     */
    public static function eq($value)
    {
        return self::cond(self::OPERATOR_EQ, $value);
    }

    /**
     * @param string $value
     * @return \DbWhereCond
     */
    public static function ne($value)
    {
        return self::cond(self::OPERATOR_NE, $value);
    }

    /**
     * @param string $value
     * @return \DbWhereCond
     */
    public static function lt($value)
    {
        return self::cond(self::OPERATOR_LT, $value);
    }

    /**
     * @param string $value
     * @return \DbWhereCond
     */
    public static function lte($value)
    {
        return self::cond(self::OPERATOR_LTE, $value);
    }

    /**
     * @param string $value
     * @return \DbWhereCond
     */
    public static function gt($value)
    {
        return self::cond(self::OPERATOR_GT, $value);
    }

    /**
     * @param string $value
     * @return \DbWhereCond
     */
    public static function gte($value)
    {
        return self::cond(self::OPERATOR_GTE, $value);
    }

    /**
     * @return \DbWhereCond
     */
    public static function in()
    {
        $args = func_get_args();
        if ((count($args) == 1) && (is_array($args[0])))
            $args = $args[0];

        return self::cond(self::OPERATOR_IN, $args);
    }

   /**
     * @return \DbWhereCond
     */
    public static function not_in()
    {
        $args = func_get_args();
        if ((count($args) == 1) && (is_array($args[0])))
            $args = $args[0];

        return self::cond(self::OPERATOR_NOT_IN, $args);
    }

    /**
     * @param int $val1
     * @param int $val2
     * @return \DbWhereCond
     */
    public static function between($val1, $val2)
    {
        return self::cond(self::OPERATOR_BETWEEN, $val1, $val2);
    }

    /**
     * @param string $value
     * @return \DbWhereCond
     */
    public static function like($value)
    {
        return self::cond(self::OPERATOR_LIKE, $value);
    }

    /**
     * @return \DbWhereCond
     */
    public static function is_null()
    {
        return self::cond(self::OPERATOR_IN, array());
    }

    /**
     * @return \DbWhereCond
     */
    public static function not_null()
    {
        return self::cond(self::OPERATOR_NOT_IN, array());
    }
}