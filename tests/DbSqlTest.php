<?php
/*
 * @author Felix A. Milovanov
 */
require_once(__DIR__ . '/Abstract.php');
require_once(dirname(__DIR__) . '/SimpleORM/DbSql.php');

class MyPDO extends PDO
{
    public $begin_transaction = 0;
    public $commit = 0;
    public $rollback = 0;

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


}

class Test_DbSql extends Test_Abstract
{
    public function testStartStransaction()
    {
        $pdo = new MyPDO();
        $this->assertEquals(0, $pdo->begin_transaction);

        $adapter = new DbSql($pdo);
        $this->assertEquals(0, $pdo->begin_transaction);

        $adapter->beginTransaction();
        $this->assertEquals(1, $pdo->begin_transaction);

        $adapter->beginTransaction();
        $this->assertEquals(2, $pdo->begin_transaction);
    }

    public function testCommit()
    {
        $pdo = new MyPDO();
        $this->assertEquals(0, $pdo->commit);

        $adapter = new DbSql($pdo);
        $this->assertEquals(0, $pdo->commit);

        $adapter->commit();
        $this->assertEquals(1, $pdo->commit);

        $adapter->commit();
        $this->assertEquals(2, $pdo->commit);
    }

    public function testRollBack()
    {
        $pdo = new MyPDO();
        $this->assertEquals(0, $pdo->rollback);

        $adapter = new DbSql($pdo);
        $this->assertEquals(0, $pdo->rollback);

        $adapter->rollBack();
        $this->assertEquals(1, $pdo->rollback);

        $adapter->rollBack();
        $this->assertEquals(2, $pdo->rollback);
    }


}
