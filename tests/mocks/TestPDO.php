<?php
/**
 * @author Felix A. Milovanov <fmilovanov@yahoo.com>
 */

class TestPDO extends PDO
{
    public $error;


    public $begin_transaction = 0;
    public $commit = 0;
    public $rollback = 0;

    public $statements = array();

    public function __construct()
    {
    }

    public function beginTransaction()
    {
        $this->begin_transaction += 1;
    }

    public function commit()
    {
        $this->commit += 1;
    }

    public function rollBack()
    {
        $this->rollback += 1;
    }

    /**
     * @param string $statement
     * @return \TestSTMT
     */
    public function prepare($statement)
    {
        $stmt = $this->statements[] = new TestSTMT($statement, $this->error);
        return $stmt;
    }

}
