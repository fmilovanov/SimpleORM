<?php
/*
 * @author Felix A. Milovanov
 */

class DbSql implements IDbAdapter
{
    const ERROR_DATA            = 'No data';
    const ERROR_WHERE           = 'No where';
    const ERROR_KEY_NOT_SCALAR  = '`%s` is not scalar';
    const ERROR_WHERE_TYPE      = 'Where type mismatch';
    const ERROR_OPERATOR        = 'Unknown operator';
    const ERROR_ORDER           = 'bad order';
    const ERROR_DELETE_ALL      = 'delete all is not allowed';

    private static $op_mapping = array(
        \DbSelect::OPERATOR_EQ      => '=',
        \DbSelect::OPERATOR_NE      => '!=',
        \DbSelect::OPERATOR_LT      => '<',
        \DbSelect::OPERATOR_LTE     => '<=',
        \DbSelect::OPERATOR_GT      => '>',
        \DbSelect::OPERATOR_GTE     => '>=',
        \DbSelect::OPERATOR_LIKE    => 'LIKE'
    );


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
                throw new \Exception(sprintf(self::ERROR_KEY_NOT_SCALAR, $key));

            $SQLStr .= ', `' . $key . "` = :c$i";
            $params[":c$i"] = $value;
            $i += 1;
        }

        return $SQLStr ? substr($SQLStr, 2) : '';
    }

    private function _parseSearchArray($key, array $values, &$pcount, array &$params, &$is_null)
    {
        $result = array();
        foreach ($values as $value)
        {
            if (is_scalar($value))
            {
                $params[":w$pcount"] = $value;
                $result[] = ":w$pcount";
                $pcount += 1;
            }
            elseif (is_null($value))
            {
                $is_null = true;
            }
            else
            {
                $key = preg_replace('/^`/', '', $key);
                $key = preg_replace('/`$/', '', $key);
                throw new \Exception(sprintf(self::ERROR_KEY_NOT_SCALAR, $key));
            }
        }

        return $result;
    }

    private function _whereOperand(\DbWhereCond $cond, array &$params, &$pcount, $tbl)
    {
        $field = '`' . $cond->key . '`';
        $value = $cond->val1;
        switch ($cond->operator)
        {
            case \DbSelect::OPERATOR_EQ:
            case \DbSelect::OPERATOR_NE:
                if (is_null($value))
                {
                    $where = "$tbl.$field IS " . ($cond->operator == \DbSelect::OPERATOR_NE ? "NOT " : "") . "NULL";
                    break;
                }

                // intentionally no "break" here -- LT/GT/etc will handle EQ/NE

            case \DbSelect::OPERATOR_LT:
            case \DbSelect::OPERATOR_LTE:
            case \DbSelect::OPERATOR_GT:
            case \DbSelect::OPERATOR_GTE:
            case \DbSelect::OPERATOR_LIKE:
                if (!is_scalar($value))
                    throw new \Exception(sprintf(self::ERROR_KEY_NOT_SCALAR, $cond->key));

                $where = "$tbl.$field " . self::$op_mapping[$cond->operator] . " :w$pcount";
                $params[":w$pcount"] = $cond->val1;
                $pcount += 1;
                break;

            case \DbSelect::OPERATOR_IN:
                $values = $this->_parseSearchArray($field, $value, $pcount, $params, $is_null);
                if (count($values))
                {
                    $where = "$tbl.$field IN (" . implode(', ' , $values) . ")";
                    if ($is_null)
                    {
                        $where = "($tbl.$field IS NULL OR ($where)";
                    }
                }
                elseif ($is_null || !count($values))
                {
                    $where = "$tbl.$field IS NULL";
                }
                break;

            case \DbSelect::OPERATOR_NOT_IN:
                $values = $this->_parseSearchArray($field, $value, $pcount, $params, $is_null);
                if (count($values))
                {
                    $where = "$tbl.$field NOT IN (" . implode(', ' , $values) . ")";
                    if ($is_null)
                    {
                        $where = "$tbl.$field IS NOT NULL AND $where";
                    }
                }
                elseif ($is_null || !count($values))
                {
                    $where = "$tbl.$field IS NOT NULL";
                }
                break;

            case \DbSelect::OPERATOR_BETWEEN:
                if (!is_scalar($cond->val1) || !is_scalar($cond->val2))
                    throw new \Exception(sprintf(self::ERROR_KEY_NOT_SCALAR, $cond->key));

                $where = "$tbl.$field BETWEEN :w$pcount AND :w" . ($pcount + 1);
                $params[":w$pcount"] = $cond->val1;
                $params[":w" . ($pcount + 1)] = $cond->val2;
                $pcount += 1;
                break;

            default:
                throw new \Exception('Unknown type');
        }

        return $where;
    }

    private function _whereClause(array $data, array &$params, $tbl)
    {
        $pcount = 0;
        $SQLStr = '';
        foreach ($data as $cond)
        {
            if (!is_array($cond))
                throw new Exception(self::ERROR_WHERE_TYPE);

            $operands = array();
            foreach ($cond as $cond)
            {
                if (!($cond instanceof \DbWhereCond))
                    throw new Exception(self::ERROR_WHERE_TYPE);

                $operands[] = $this->_whereOperand($cond, $params, $pcount, $tbl);
            }

            if (count($operands) > 1)
                $SQLStr .= ' AND (' . implode(' OR ', $operands) . ')';
            else
                $SQLStr .= ' AND ' . $operands[0];
        }

        return $SQLStr ? substr($SQLStr, 5) : '';
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
            throw new Exception(self::ERROR_DATA);

        $params = array();
        $SQLStr = 'INSERT INTO `' . $table . '` SET ' . $this->_prepareSetClause($data, $params);

        $stmt = $this->getAdapter()->prepare($SQLStr);
        if (!$stmt->execute($params))
            $this->_throw($stmt, $SQLStr, $params);
    }

    public function update($table, array $data, array $where)
    {
        if (empty($data))
            throw new \Exception(self::ERROR_DATA);

        if (empty($where))
            throw new \Exception(self::ERROR_WHERE);

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

    public function delete($table, array $where, $allow_delete_all = false)
    {
        $i = 0;
        $SQLStr = '';
        $params = array();
        foreach ($where as $key => $value)
        {
            if (!is_scalar($value))
                throw new \Exception(sprintf(self::ERROR_KEY_NOT_SCALAR, $key));

            $SQLStr .= " AND `$key` = :w$i";
            $params[":w$i"] = $value;
            $i += 1;
        }

        if (empty($params) && !$allow_delete_all)
            throw new \Exception(self::ERROR_DELETE_ALL);

        $SQLStr = "DELETE FROM `$table`" . (empty($params) ? '' : (' WHERE ' . substr($SQLStr, 5)));
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

    public function query(\DbSelect $select)
    {
        $params = array();
        $SQLStr = "SELECT * FROM `" . $select->getTable() . "` t";
        if ($where = $this->_whereClause($select->getWhere(), $params, 't'))
            $SQLStr .= " WHERE $where";

        if ($order = $select->getOrder())
            $SQLStr .= " ORDER BY $order";

        if (is_array($limit = $select->getSearchLimit()))
            $SQLStr .= ' LIMIT ' . ($limit[1] ? ($limit[1] . ', ' . $limit[0]) : $limit[0]);

        $stmt = $this->getAdapter()->prepare($SQLStr);
        $stmt->execute($params);

        if ((int) $stmt->errorCode())
            $this->_throw ($stmt, $SQLStr, $params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

}