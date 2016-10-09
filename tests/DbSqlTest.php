<?php
/*
 * @author Felix A. Milovanov
 */
require_once(__DIR__ . '/Abstract.php');
require_once(dirname(__DIR__) . '/SimpleORM/DbSql.php');
require_once(dirname(__DIR__) . '/SimpleORM/DbException.php');

class MySTMT
{
    public $sql;
    public $params;
    public $error;

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

    public function errorInfo()
    {
        return $this->error;
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

        // try failure
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

        // try failure
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

}
