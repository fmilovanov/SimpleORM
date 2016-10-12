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

class DbJoin
{
    const TYPE_INNER    = 'INNER';
    const TYPE_LEFT     = ' LEFT';

    const ERROR_TYPE    = 'wrong type';
    const ERROR_TABLE   = 'wrong table name';
    const ERROR_COLUMNS = 'wrong join columns';
    const ERROR_ON      = 'wrong join on';

    public $type;
    public $table;
    public $columns = array();
    public $on;

    public function __construct($type, $table, array $on, $columns)
    {
        if (!is_array(array(self::TYPE_INNER, self::TYPE_LEFT)))
                throw new \Exception(self::ERROR_TYPE);
        $this->type = $type;

        // table name
        if (is_array($table))
        {
            if (!$this->isName($table[0]) || !$this->isName($table[1]))
                throw new \Exception(self::ERROR_TABLE);

            $table = array($table[0], $table[1]);
        }
        elseif (!$this->isName($table))
        {
            throw new \Exception(self::ERROR_TABLE);
        }
        $this->table = $table;

        // on clause
        $this->on = array();
        foreach ($on as $key => $value)
        {
            if (!$this->isName($key))
                throw new \Exception(self::ERROR_ON);

            if (is_array($value))
            {
                if (!isset($value[0]) || !isset($value[1]) || !$this->isName($value[0]) || !$this->isName($value[1]))
                    throw new \Exception(self::ERROR_ON);

                $this->on[$key] = $value;
            }
            elseif (is_scalar($value))
            {
                $this->on[$key] = \DbSelect::eq($value);
                $this->on[$key]->key = $key;
            }
            elseif ($value instanceof \DbWhereCond)
            {
                $this->on[$key] = $value;
                $this->on[$key]->key = $key;
            }
            else throw new \Exception(self::ERROR_ON);
        }

        // columns
        if ($columns !== '*')
        {
            if (!is_array($columns))
                throw new \Exception(self::ERROR_JOIN_COLUMNS);

            foreach ($columns as $column)
            {
                if (!$this->isName($table))
                    throw new \Exception(self::ERROR_JOIN_COLUMNS);
            }
        }
        $this->columns = $columns;
    }

    private function isName($name)
    {
        return is_string($name) && preg_match('/^[a-z]/i', $name);
    }

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

    const ERROR_JOINED      = 'you already have a join on this table';
    const ERROR_JOIN_LEFT   = 'no inner joins after left join';

    private $_table;
    private $_columns;
    private $_joins = array();
    private $_where = array();
    private $_order;
    private $_search_limit;

    private function isName($name)
    {
        return is_string($name) && preg_match('/^[a-z]/i', $name);
    }

    public function __construct($table, array $columns = null)
    {
        if (!$this->isName($table))
            throw new Exception(self::ERROR_TABLE);

        if (is_array($columns))
        {
            if (empty($columns))
                throw new \Exception(self::ERROR_EMPTY);

            foreach ($columns as $field)
            {
                if (!$this->isName($field))
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

    public function getJoins()
    {
        return $this->_joins;
    }

    private function _join($type, $table, $on, $columns)
    {
        if (!is_array($table))
            $table = array($table, 'j' . count($this->_joins));

        if (isset($this->_joins[$table[1]]))
            throw new \Exception(self::ERROR_JOINED);

        if ($type == \DbJoin::TYPE_INNER)
        {
            foreach ($this->_joins as $_join)
            {
                if ($_join->type == \DbJoin::TYPE_LEFT)
                    throw new \Exception(self::ERROR_JOIN_LEFT);
            }
        }


        $this->_joins[$table[1]] = new \DbJoin($type, $table[0], $on, $columns);
    }

    public function join($table, $on, $columns = array())
    {
        $this->_join(\DbJoin::TYPE_INNER, $table, $on, $columns);
    }

    public function joinLeft($table, $on, $columns = array())
    {
        $this->_join(\DbJoin::TYPE_LEFT, $table, $on, $columns);
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