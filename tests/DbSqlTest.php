<?php
/*
 * @author Felix A. Milovanov
 */
require_once(__DIR__ . '/Abstract.php');
require_once(dirname(__DIR__) . '/SimpleORM/DbSql.php');
require_once(dirname(__DIR__) . '/SimpleORM/DbSelect.php');
require_once(dirname(__DIR__) . '/SimpleORM/DbException.php');

class MySTMT
{
    public $sql;
    public $params;
    public $error;
    public $fetch_all;

    public function __construct($sql, $error = null)
    {
        $this->sql = $sql;
        $this->error = $error;
    }

    public function execute($params = array())
    {
        $this->params = $params;
        return $this->error ? false : true;
    }

    public function errorCode()
    {
        return $this->error ? $this->error[0] : null;
    }

    public function errorInfo()
    {
        return $this->error;
    }

    public function fetchAll($type)
    {
        $this->fetch_all = $type;
    }
}

class MyPDO extends PDO
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
     * @return \MySTMT
     */
    public function prepare($statement)
    {
        $stmt = $this->statements[] = new MySTMT($statement, $this->error);
        return $stmt;
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

    public function testInsert()
    {
        $pdo = new MyPDO();
        $adapter = new DbSql($pdo);
        $this->assertCount(0, $pdo->statements);

        $table = $this->randValue();
        $data = array('x1' => $this->randValue(), 'x2' => $this->randValue());

        // try insert
        $adapter->insert($table, $data);
        $this->assertCount(1, $pdo->statements);
        $this->assertTrue($pdo->statements[0] instanceof MySTMT);

        $stmt = $pdo->statements[0];
        $this->assertEquals("INSERT INTO `$table` SET `x1` = :c0, `x2` = :c1", $stmt->sql);
        $this->assertEquals(array(':c0' => $data['x1'], ':c1' => $data['x2']), $stmt->params);

        // try empty insert
        try
        {
            $adapter->insert($table, array());
            $this->fail();
        }
        catch (\Exception $e)
        {
            $this->assertEquals(\DbSql::ERROR_DATA, $e->getMessage());
        }

        // try non-scalar value
        try
        {
            $adapter->insert($table, array($fname = $this->randValue() => array()));
            $this->fail();
        }
        catch (\Exception $e)
        {
            $this->assertEquals(sprintf(\DbSql::ERROR_KEY_NOT_SCALAR, $fname), $e->getMessage());
        }

        // try driver failure
        $pdo->error = array(rand(10, 90), rand(100, 999), $this->randValue());
        try
        {
            $adapter->insert($table, $data);
            $this->fail();
        }
        catch (\DbException $e)
        {
            $this->assertEquals($pdo->error[1], $e->getCode());
            $this->assertEquals($pdo->error[2], $e->getMessage());
            $this->assertEquals("INSERT INTO `$table` SET `x1` = :c0, `x2` = :c1", $e->getSQL());
            $this->assertEquals(array(':c0' => $data['x1'], ':c1' => $data['x2']), $e->getParams());

            // check that is was executed same way
            $this->assertCount(2, $pdo->statements);
            $this->assertTrue($pdo->statements[1] instanceof MySTMT);

            $stmt = $pdo->statements[1];
            $this->assertEquals("INSERT INTO `$table` SET `x1` = :c0, `x2` = :c1", $stmt->sql);
            $this->assertEquals(array(':c0' => $data['x1'], ':c1' => $data['x2']), $stmt->params);
        }
    }

    public function testUpdate()
    {
        $pdo = new MyPDO();
        $adapter = new DbSql($pdo);
        $this->assertCount(0, $pdo->statements);

        $id = rand(10, 99);
        $table = $this->randValue();
        $data = array('x1' => $this->randValue(), 'x2' => $this->randValue());

        // try update
        $adapter->update($table, $data, array('id' => $id));
        $this->assertCount(1, $pdo->statements);
        $this->assertTrue($pdo->statements[0] instanceof MySTMT);

        $stmt = $pdo->statements[0];
        $this->assertEquals("UPDATE `$table` SET `x1` = :c0, `x2` = :c1 WHERE `id` = :w0", $stmt->sql);
        $this->assertEquals(array(':c0' => $data['x1'], ':c1' => $data['x2'], ':w0' => $id), $stmt->params);

        // more in where clause
        $id2 = rand(100, 999);
        $adapter->update($table, $data, array('id' => $id, 'id2' => $id2));
        $this->assertCount(2, $pdo->statements);
        $this->assertTrue($pdo->statements[1] instanceof MySTMT);

        $stmt = $pdo->statements[1];
        $this->assertEquals("UPDATE `$table` SET `x1` = :c0, `x2` = :c1 WHERE `id` = :w0 AND `id2` = :w1", $stmt->sql);
        $this->assertEquals(array(':c0' => $data['x1'], ':c1' => $data['x2'], ':w0' => $id, ':w1' => $id2),
                            $stmt->params);

        // try empty update
        try
        {
            $adapter->update($table, array(), array('id' => 1));
            $this->fail();
        }
        catch (\Exception $e)
        {
            $this->assertEquals(\DbSql::ERROR_DATA, $e->getMessage());
        }

        // try empty where
        try
        {
            $adapter->update($table, $data, array());
            $this->fail();
        }
        catch (\Exception $e)
        {
            $this->assertEquals(\DbSql::ERROR_WHERE, $e->getMessage());
        }

        // try non-scalar value
        try
        {
            $adapter->update($table, array($fname = $this->randValue() => array()), array('id' => $id));
            $this->fail();
        }
        catch (\Exception $e)
        {
            $this->assertEquals(sprintf(\DbSql::ERROR_KEY_NOT_SCALAR, $fname), $e->getMessage());
        }

        // try driver failure
        $pdo->error = array(rand(10, 90), rand(100, 999), $this->randValue());
        try
        {
            $adapter->update($table, $data, array('id' => $id));
            $this->fail();
        }
        catch (\DbException $e)
        {
            $this->assertEquals($pdo->error[1], $e->getCode());
            $this->assertEquals($pdo->error[2], $e->getMessage());
            $this->assertEquals("UPDATE `$table` SET `x1` = :c0, `x2` = :c1 WHERE `id` = :w0", $e->getSQL());
            $this->assertEquals(array(':c0' => $data['x1'], ':c1' => $data['x2'], ':w0' => $id), $e->getParams());

            // check that is was executed same way
            $this->assertCount(3, $pdo->statements);
            $this->assertTrue($pdo->statements[1] instanceof MySTMT);

            $stmt = $pdo->statements[2];
            $this->assertEquals("UPDATE `$table` SET `x1` = :c0, `x2` = :c1 WHERE `id` = :w0", $stmt->sql);
            $this->assertEquals(array(':c0' => $data['x1'], ':c1' => $data['x2'], ':w0' => $id), $stmt->params);
        }
    }

    public function testQuery()
    {
        $pdo = new MyPDO();
        $adapter = new DbSql($pdo);
        $this->assertCount(0, $pdo->statements);

        // try whereless select
        $select = new \DbSelect($tbl = 'tbl' . rand(100, 999));
        $adapter->query($select);
        $this->assertCount(1, $pdo->statements);
        $this->assertTrue($pdo->statements[0] instanceof \MySTMT);

        $stmt = $pdo->statements[0];
        $this->assertEquals("SELECT * FROM `$tbl`", $stmt->sql);
        $this->assertEquals(array(), $stmt->params);
        $this->assertEquals(PDO::FETCH_ASSOC, $stmt->fetch_all);

        // add a param
        $select->where($k1 = 'key' . rand(10, 19), $v1 = $this->randValue());
        $adapter->query($select);
        $this->assertCount(2, $pdo->statements);
        $this->assertTrue($pdo->statements[1] instanceof \MySTMT);

        $stmt = $pdo->statements[1];
        $this->assertEquals("SELECT * FROM `$tbl` WHERE `$k1` = :w0", $stmt->sql);
        $this->assertEquals(array(':w0' => $v1), $stmt->params);
        $this->assertEquals(PDO::FETCH_ASSOC, $stmt->fetch_all);

        // another param
        $select->whereOr($k2 = 'key' . rand(20, 29), $v2 = $this->randValue());
        $adapter->query($select);
        $this->assertCount(3, $pdo->statements);
        $this->assertTrue($pdo->statements[2] instanceof \MySTMT);

        $stmt = $pdo->statements[2];
        $this->assertEquals("SELECT * FROM `$tbl` WHERE (`$k1` = :w0 OR `$k2` = :w1)", $stmt->sql);
        $this->assertEquals(array(':w0' => $v1, ':w1' => $v2), $stmt->params);
        $this->assertEquals(PDO::FETCH_ASSOC, $stmt->fetch_all);

        // add LT
        $select->where($k3 = 'key' . rand(30, 39), \DbSelect::lt($v3 = rand(1000, 9999)));
        $adapter->query($select);
        $this->assertCount(4, $pdo->statements);
        $this->assertTrue($pdo->statements[3] instanceof \MySTMT);

        $stmt = $pdo->statements[3];
        $this->assertEquals("SELECT * FROM `$tbl` WHERE (`$k1` = :w0 OR `$k2` = :w1) AND `$k3` < :w2", $stmt->sql);
        $this->assertEquals(array(':w0' => $v1, ':w1' => $v2, ':w2' => $v3), $stmt->params);
        $this->assertEquals(PDO::FETCH_ASSOC, $stmt->fetch_all);

        // LTE
        $select->where($k4 = 'key' . rand(40, 49), \DbSelect::lte($v4 = rand(1000, 9999)));
        $adapter->query($select);
        $this->assertCount(5, $pdo->statements);
        $this->assertTrue($pdo->statements[4] instanceof \MySTMT);

        $stmt = $pdo->statements[4];
        $this->assertEquals("SELECT * FROM `$tbl` WHERE (`$k1` = :w0 OR `$k2` = :w1) AND `$k3` < :w2 AND `$k4` <= :w3",
                            $stmt->sql);
        $this->assertEquals(array(':w0' => $v1, ':w1' => $v2, ':w2' => $v3, ':w3' => $v4), $stmt->params);
        $this->assertEquals(PDO::FETCH_ASSOC, $stmt->fetch_all);

        // GT
        $select->where($k5 = 'key' . rand(50, 59), \DbSelect::gt($v5 = rand(1000, 9999)));
        $adapter->query($select);
        $this->assertCount(6, $pdo->statements);
        $this->assertTrue($pdo->statements[5] instanceof \MySTMT);

        $stmt = $pdo->statements[5];
        $this->assertEquals("SELECT * FROM `$tbl` WHERE (`$k1` = :w0 OR `$k2` = :w1) AND `$k3` < :w2 AND `$k4` <= :w3" .
                            " AND `$k5` > :w4", $stmt->sql);
        $this->assertEquals(array(':w0' => $v1, ':w1' => $v2, ':w2' => $v3, ':w3' => $v4, ':w4' => $v5), $stmt->params);
        $this->assertEquals(PDO::FETCH_ASSOC, $stmt->fetch_all);

        // GTE
        $select->where($k6 = 'key' . rand(60, 69), \DbSelect::gte($v6 = rand(1000, 9999)));
        $adapter->query($select);
        $this->assertCount(7, $pdo->statements);
        $this->assertTrue($pdo->statements[6] instanceof \MySTMT);

        $stmt = $pdo->statements[6];
        $this->assertEquals("SELECT * FROM `$tbl` WHERE (`$k1` = :w0 OR `$k2` = :w1) AND `$k3` < :w2 AND `$k4` <= :w3" .
                            " AND `$k5` > :w4 AND `$k6` >= :w5", $stmt->sql);
        $this->assertEquals(array(':w0' => $v1, ':w1' => $v2, ':w2' => $v3, ':w3' => $v4, ':w4' => $v5, ':w5' => $v6),
                            $stmt->params);
        $this->assertEquals(PDO::FETCH_ASSOC, $stmt->fetch_all);

        // add IN array
        $select->where($k7 = 'key' . rand(70, 79), \DbSelect::in($v71 = rand(10, 99), $v72 = rand(100, 999)));
        $adapter->query($select);
        $this->assertCount(8, $pdo->statements);
        $this->assertTrue($pdo->statements[7] instanceof \MySTMT);

        $stmt = $pdo->statements[7];
        $this->assertEquals("SELECT * FROM `$tbl` WHERE (`$k1` = :w0 OR `$k2` = :w1) AND `$k3` < :w2 AND `$k4` <= :w3" .
                            " AND `$k5` > :w4 AND `$k6` >= :w5 AND `$k7` IN (:w6, :w7)", $stmt->sql);
        $this->assertEquals(array(':w0' => $v1, ':w1' => $v2, ':w2' => $v3, ':w3' => $v4, ':w4' => $v5, ':w5' => $v6,
                            ':w6' => $v71, ':w7' => $v72), $stmt->params);
        $this->assertEquals(PDO::FETCH_ASSOC, $stmt->fetch_all);










    }

}
