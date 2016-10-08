<?php
/**
 * @author Felix A. Milovanov
 */

Interface IDbAdapter
{
    public function insert($table, array $data);

    public function update($table, array $data, array $where);

    public function lastInsertId();

    public function startTransaction();
    public function commtTransaction();
    public function rollbackTransaction();
}