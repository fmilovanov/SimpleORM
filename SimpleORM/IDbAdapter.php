<?php
/**
 * @author Felix A. Milovanov
 */

Interface IDbAdapter
{
    public function insert($table, array $data);

    public function update($table, array $data, array $where);

    public function lastInsertId();

    public function beginTransaction();
    public function commit();
    public function rollBack();
}