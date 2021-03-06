<?php
/*
 * @author Felix A. Milovanov
 */
require_once(__DIR__ . '/Abstract.php');
require_once(dirname(__DIR__) . '/SimpleORM/DbSql.php');
require_once(dirname(__DIR__) . '/SimpleORM/DbSelect.php');
require_once(dirname(__DIR__) . '/SimpleORM/DbException.php');

require_once(__DIR__ . '/mocks/TestSTMT.php');
require_once(__DIR__ . '/mocks/TestPDO.php');

class Test_DbSql extends Test_Abstract
{
    public function testStartStransaction()
    {
        $pdo = new TestPDO();
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
        $pdo = new TestPDO();
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
        $pdo = new TestPDO();
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
        $pdo = new TestPDO();
        $adapter = new DbSql($pdo);
        $this->assertCount(0, $pdo->statements);

        $table = $this->randValue();
        $data = array('x1' => $this->randValue(), 'x2' => $this->randValue());

        // try insert
        $adapter->insert($table, $data);
        $this->assertCount(1, $pdo->statements);
        $this->assertTrue($pdo->statements[0] instanceof TestSTMT);

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
            $this->assertTrue($pdo->statements[1] instanceof TestSTMT);

            $stmt = $pdo->statements[1];
            $this->assertEquals("INSERT INTO `$table` SET `x1` = :c0, `x2` = :c1", $stmt->sql);
            $this->assertEquals(array(':c0' => $data['x1'], ':c1' => $data['x2']), $stmt->params);
        }
    }

    public function testUpdate()
    {
        $pdo = new TestPDO();
        $adapter = new DbSql($pdo);
        $this->assertCount(0, $pdo->statements);

        $id = rand(10, 99);
        $table = $this->randValue();
        $data = array('x1' => $this->randValue(), 'x2' => $this->randValue());

        // try update
        $adapter->update($table, $data, array('id' => $id));
        $this->assertCount(1, $pdo->statements);
        $this->assertTrue($pdo->statements[0] instanceof TestSTMT);

        $stmt = $pdo->statements[0];
        $this->assertEquals("UPDATE `$table` SET `x1` = :c0, `x2` = :c1 WHERE `id` = :w0", $stmt->sql);
        $this->assertEquals(array(':c0' => $data['x1'], ':c1' => $data['x2'], ':w0' => $id), $stmt->params);

        // more in where clause
        $id2 = rand(100, 999);
        $adapter->update($table, $data, array('id' => $id, 'id2' => $id2));
        $this->assertCount(2, $pdo->statements);
        $this->assertTrue($pdo->statements[1] instanceof TestSTMT);

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
            $this->assertTrue($pdo->statements[1] instanceof TestSTMT);

            $stmt = $pdo->statements[2];
            $this->assertEquals("UPDATE `$table` SET `x1` = :c0, `x2` = :c1 WHERE `id` = :w0", $stmt->sql);
            $this->assertEquals(array(':c0' => $data['x1'], ':c1' => $data['x2'], ':w0' => $id), $stmt->params);
        }
    }

    public function testDelete()
    {
        $pdo = new TestPDO();
        $adapter = new DbSql($pdo);

        $SQLStr = '';
        $where = $sql = $params = array();
        for ($i = rand(2, 7); $i >= 0; $i--)
        {
            $key = 'x' . rand(100, 999);
            $val = $this->randValue();

            $SQLStr .= " AND `$key` = :w" . count($params);
            $params[':w' . count($params)] = $val;
            $where[$key] = $val;
        }

        $table = 'tbl' . rand(100, 999);
        $SQLStr = "DELETE FROM `$table` WHERE " . substr($SQLStr, 5);

        $adapter->delete($table, $where);
        $this->assertInstanceOf('TestSTMT', $stmt = array_pop($pdo->statements));
        $this->assertEquals($SQLStr, $stmt->sql);
        $this->assertEquals($params, $stmt->params);
        $this->assertNull($stmt->fetch_all);

        // try empty delete
        try
        {
            $adapter->delete($table, array());
            $this->fail();
        }
        catch (\Exception $e)
        {
            $this->assertEquals(\DbSql::ERROR_DELETE_ALL, $e->getMessage());
            $this->assertCount(0, $pdo->statements);
        }

        // confirmed empty delete
        $adapter->delete($table, array(), true);
        $this->assertInstanceOf('TestSTMT', $stmt = array_pop($pdo->statements));
        $this->assertEquals("DELETE FROM `$table`", $stmt->sql);
        $this->assertEquals(array(), $stmt->params);
        $this->assertNull($stmt->fetch_all);
    }

    protected function assertQuery($expected, $actual, $message = '')
    {
        $expected = preg_replace('/\s+/', ' ', $expected);
        $actual = preg_replace('/\s+/', ' ', $actual);

        $this->assertEquals($expected, $actual, $message);
    }

    public function testQueryFrom()
    {
        $pdo = new TestPDO();
        $adapter = new DbSql($pdo);
        $select = new \DbSelect($tbl = 'tbl' . rand(100, 999));
        $adapter->query($select);
        $this->assertInstanceOf('TestSTMT', $stmt = array_pop($pdo->statements));
        $this->assertQuery("SELECT t.* FROM `$tbl` t", $stmt->sql);

        // generate columns
        $fields = '';
        $columns = array();
        for ($i = rand(3, 8); $i >= 0; $i--)
        {
            $col = $columns[] = 'x' . rand($i * 10, $i * 10 + 10);
            $fields .= ", t.`$col`";
        }
        $select = new \DbSelect($tbl = 'tbl' . rand(100, 999), $columns);
        $adapter->query($select);
        $this->assertInstanceOf('TestSTMT', $stmt = array_pop($pdo->statements));
        $this->assertQuery("SELECT " . substr($fields, 2) . " FROM `$tbl` t", $stmt->sql);

        // column aliases
        $fields = $columns = array();
        for ($i = rand(3, 8); $i >= 0; $i--)
        {
            $col = 'x' . rand($i * 10, $i * 10 + 10);
            $alias = 'a' . rand($i * 10, $i * 10 + 10);
            $fields[] = "t.`$col` `$alias`";
            $columns[$col] = $alias;
        }
        $select = new \DbSelect($tbl = 'tbl' . rand(100, 999), $columns);
        $adapter->query($select);
        $this->assertInstanceOf('TestSTMT', $stmt = array_pop($pdo->statements));
        $this->assertQuery("SELECT " . implode(', ', $fields) . " FROM `$tbl` t", $stmt->sql);
    }

    public function testQueryEq()
    {
        $pdo = new TestPDO();
        $adapter = new DbSql($pdo);
        $select = new \DbSelect($tbl = 'tbl' . rand(100, 999));
        $select->where($k1 = 'key' . rand(10, 19), $v1 = rand(100, 199));
        $adapter->query($select);
        $this->assertInstanceOf('TestSTMT', $stmt = array_pop($pdo->statements));
        $this->assertQuery("SELECT t.* FROM `$tbl` t WHERE t.`$k1` = :w0", $stmt->sql);
        $this->assertEquals(array(':w0' => $v1), $stmt->params);
        $this->assertEquals(PDO::FETCH_ASSOC, $stmt->fetch_all);

        // add via class
        $select->where($k2 = 'key' . rand(20, 29), \DbSelect::eq($v2 = rand(200, 299)));
        $adapter->query($select);
        $this->assertInstanceOf('TestSTMT', $stmt = array_pop($pdo->statements));
        $this->assertQuery("SELECT t.* FROM `$tbl` t WHERE t.`$k1` = :w0 AND t.`$k2` = :w1", $stmt->sql);
        $this->assertEquals(array(':w0' => $v1, ':w1' => $v2), $stmt->params);
        $this->assertEquals(PDO::FETCH_ASSOC, $stmt->fetch_all);

        // add NULL
        $select->where($k3 = 'key' . rand(30, 39), \DbSelect::eq(null));
        $adapter->query($select);
        $this->assertInstanceOf('TestSTMT', $stmt = array_pop($pdo->statements));
        $this->assertQuery("SELECT t.* FROM `$tbl` t WHERE t.`$k1` = :w0 AND t.`$k2` = :w1 AND t.`$k3` IS NULL",
                            $stmt->sql);
        $this->assertEquals(array(':w0' => $v1, ':w1' => $v2), $stmt->params);
        $this->assertEquals(PDO::FETCH_ASSOC, $stmt->fetch_all);

        // try not scalar
        $select->where($k4 = 'key' . rand(40, 49), \DbSelect::eq(array(-1)));
        try
        {
            $adapter->query($select);
            $this->fail();
        }
        catch (\Exception $e)
        {
            $this->assertEquals(sprintf(\DbSql::ERROR_KEY_NOT_SCALAR, $k4), $e->getMessage());
        }
    }

    public function testQueryNe()
    {
        $pdo = new TestPDO();
        $adapter = new DbSql($pdo);
        $select = new \DbSelect($tbl = 'tbl' . rand(100, 999));
        $select->where($k1 = 'key' . rand(10, 19), \DbSelect::ne($v1 = rand(100, 199)));
        $adapter->query($select);
        $this->assertInstanceOf('TestSTMT', $stmt = array_pop($pdo->statements));
        $this->assertQuery("SELECT t.* FROM `$tbl` t WHERE t.`$k1` != :w0", $stmt->sql);
        $this->assertEquals(array(':w0' => $v1), $stmt->params);
        $this->assertEquals(PDO::FETCH_ASSOC, $stmt->fetch_all);

        // add NULL
        $select->where($k3 = 'key' . rand(30, 39), \DbSelect::ne(null));
        $adapter->query($select);
        $this->assertInstanceOf('TestSTMT', $stmt = array_pop($pdo->statements));
        $this->assertQuery("SELECT t.* FROM `$tbl` t WHERE t.`$k1` != :w0 AND t.`$k3` IS NOT NULL", $stmt->sql);
        $this->assertEquals(array(':w0' => $v1), $stmt->params);
        $this->assertEquals(PDO::FETCH_ASSOC, $stmt->fetch_all);

        // try not scalar
        $select->where($k4 = 'key' . rand(40, 49), \DbSelect::ne(array(-1)));
        try
        {
            $adapter->query($select);
            $this->fail();
        }
        catch (\Exception $e)
        {
            $this->assertEquals(sprintf(\DbSql::ERROR_KEY_NOT_SCALAR, $k4), $e->getMessage());
        }
    }

    public function testQueryLt()
    {
        $pdo = new TestPDO();
        $adapter = new DbSql($pdo);
        $select = new \DbSelect($tbl = 'tbl' . rand(100, 999));
        $select->where($k1 = 'key' . rand(10, 19), \DbSelect::lt($v1 = rand(100, 199)));
        $adapter->query($select);
        $this->assertInstanceOf('TestSTMT', $stmt = array_pop($pdo->statements));
        $this->assertQuery("SELECT t.* FROM `$tbl` t WHERE t.`$k1` < :w0", $stmt->sql);
        $this->assertEquals(array(':w0' => $v1), $stmt->params);
        $this->assertEquals(PDO::FETCH_ASSOC, $stmt->fetch_all);

        // try not scalar
        $select->where($k4 = 'key' . rand(40, 49), \DbSelect::lt(array(-1)));
        try
        {
            $adapter->query($select);
            $this->fail();
        }
        catch (\Exception $e)
        {
            $this->assertEquals(sprintf(\DbSql::ERROR_KEY_NOT_SCALAR, $k4), $e->getMessage());
        }
    }

    public function testQueryLte()
    {
        $pdo = new TestPDO();
        $adapter = new DbSql($pdo);
        $select = new \DbSelect($tbl = 'tbl' . rand(100, 999));
        $select->where($k1 = 'key' . rand(10, 19), \DbSelect::lte($v1 = rand(100, 199)));
        $adapter->query($select);
        $this->assertInstanceOf('TestSTMT', $stmt = array_pop($pdo->statements));
        $this->assertQuery("SELECT t.* FROM `$tbl` t WHERE t.`$k1` <= :w0", $stmt->sql);
        $this->assertEquals(array(':w0' => $v1), $stmt->params);
        $this->assertEquals(PDO::FETCH_ASSOC, $stmt->fetch_all);

        // try not scalar
        $select->where($k4 = 'key' . rand(40, 49), \DbSelect::lte(array(-1)));
        try
        {
            $adapter->query($select);
            $this->fail();
        }
        catch (\Exception $e)
        {
            $this->assertEquals(sprintf(\DbSql::ERROR_KEY_NOT_SCALAR, $k4), $e->getMessage());
        }
    }

    public function testQueryGt()
    {
        $pdo = new TestPDO();
        $adapter = new DbSql($pdo);
        $select = new \DbSelect($tbl = 'tbl' . rand(100, 999));
        $select->where($k1 = 'key' . rand(10, 19), \DbSelect::gt($v1 = rand(100, 199)));
        $adapter->query($select);
        $this->assertInstanceOf('TestSTMT', $stmt = array_pop($pdo->statements));
        $this->assertQuery("SELECT t.* FROM `$tbl` t WHERE t.`$k1` > :w0", $stmt->sql);
        $this->assertEquals(array(':w0' => $v1), $stmt->params);
        $this->assertEquals(PDO::FETCH_ASSOC, $stmt->fetch_all);

        // try not scalar
        $select->where($k4 = 'key' . rand(40, 49), \DbSelect::gt(array(-1)));
        try
        {
            $adapter->query($select);
            $this->fail();
        }
        catch (\Exception $e)
        {
            $this->assertEquals(sprintf(\DbSql::ERROR_KEY_NOT_SCALAR, $k4), $e->getMessage());
        }
    }

    public function testQueryGte()
    {
        $pdo = new TestPDO();
        $adapter = new DbSql($pdo);
        $select = new \DbSelect($tbl = 'tbl' . rand(100, 999));
        $select->where($k1 = 'key' . rand(10, 19), \DbSelect::gte($v1 = rand(100, 199)));
        $adapter->query($select);
        $this->assertInstanceOf('TestSTMT', $stmt = array_pop($pdo->statements));
        $this->assertQuery("SELECT t.* FROM `$tbl` t WHERE t.`$k1` >= :w0", $stmt->sql);
        $this->assertEquals(array(':w0' => $v1), $stmt->params);
        $this->assertEquals(PDO::FETCH_ASSOC, $stmt->fetch_all);

        // try not scalar
        $select->where($k4 = 'key' . rand(40, 49), \DbSelect::gte(array(-1)));
        try
        {
            $adapter->query($select);
            $this->fail();
        }
        catch (\Exception $e)
        {
            $this->assertEquals(sprintf(\DbSql::ERROR_KEY_NOT_SCALAR, $k4), $e->getMessage());
        }
    }

    public function testQueryLike()
    {
        $pdo = new TestPDO();
        $adapter = new DbSql($pdo);
        $select = new \DbSelect($tbl = 'tbl' . rand(100, 999));
        $select->where($k1 = 'key' . rand(10, 19), \DbSelect::like($v1 = rand(100, 199)));
        $adapter->query($select);
        $this->assertInstanceOf('TestSTMT', $stmt = array_pop($pdo->statements));
        $this->assertQuery("SELECT t.* FROM `$tbl` t WHERE t.`$k1` LIKE :w0", $stmt->sql);
        $this->assertEquals(array(':w0' => $v1), $stmt->params);
        $this->assertEquals(PDO::FETCH_ASSOC, $stmt->fetch_all);

        // try not scalar
        $select->where($k4 = 'key' . rand(40, 49), \DbSelect::like(array(-1)));
        try
        {
            $adapter->query($select);
            $this->fail();
        }
        catch (\Exception $e)
        {
            $this->assertEquals(sprintf(\DbSql::ERROR_KEY_NOT_SCALAR, $k4), $e->getMessage());
        }
    }

    public function testQueryIn()
    {
        $pdo = new TestPDO();
        $adapter = new DbSql($pdo);
        $select = new \DbSelect($tbl = 'tbl' . rand(100, 999));
        $select->where($k1 = 'key' . rand(10, 19), array($v11 = rand(100, 199), $v12 = rand(200, 299)));
        $adapter->query($select);
        $this->assertInstanceOf('TestSTMT', $stmt = array_pop($pdo->statements));
        $this->assertQuery("SELECT t.* FROM `$tbl` t WHERE t.`$k1` IN (:w0, :w1)", $stmt->sql);
        $this->assertEquals(array(':w0' => $v11, ':w1' => $v12), $stmt->params);
        $this->assertEquals(PDO::FETCH_ASSOC, $stmt->fetch_all);

        // add via class
        $select->where($k2 = 'key' . rand(20, 29), \DbSelect::in($v21 = rand(300, 399), $v22 = rand(400, 499)));
        $adapter->query($select);
        $this->assertInstanceOf('TestSTMT', $stmt = array_pop($pdo->statements));
        $this->assertQuery("SELECT t.* FROM `$tbl` t WHERE t.`$k1` IN (:w0, :w1) AND t.`$k2` IN (:w2, :w3)", $stmt->sql);
        $this->assertEquals(array(':w0' => $v11, ':w1' => $v12, ':w2' => $v21, ':w3' => $v22), $stmt->params);
        $this->assertEquals(PDO::FETCH_ASSOC, $stmt->fetch_all);

        // add empty array
        $select->where($k3 = 'key' . rand(30, 39), \DbSelect::in());
        $adapter->query($select);
        $this->assertInstanceOf('TestSTMT', $stmt = array_pop($pdo->statements));
        $this->assertQuery("SELECT t.* FROM `$tbl` t WHERE t.`$k1` IN (:w0, :w1) AND t.`$k2` IN (:w2, :w3)" .
                            " AND t.`$k3` IS NULL",
                            $stmt->sql);
        $this->assertEquals(array(':w0' => $v11, ':w1' => $v12, ':w2' => $v21, ':w3' => $v22), $stmt->params);
        $this->assertEquals(PDO::FETCH_ASSOC, $stmt->fetch_all);

        // add array with NULL only
        $select->where($k4 = 'key' . rand(40, 49), \DbSelect::in(null));
        $adapter->query($select);
        $this->assertInstanceOf('TestSTMT', $stmt = array_pop($pdo->statements));
        $this->assertQuery("SELECT t.* FROM `$tbl` t WHERE t.`$k1` IN (:w0, :w1) AND t.`$k2` IN (:w2, :w3)" .
                            " AND t.`$k3` IS NULL AND t.`$k4` IS NULL", $stmt->sql);
        $this->assertEquals(array(':w0' => $v11, ':w1' => $v12, ':w2' => $v21, ':w3' => $v22), $stmt->params);
        $this->assertEquals(PDO::FETCH_ASSOC, $stmt->fetch_all);

        // add array with NULL as element
        $select->where($k5 = 'key' . rand(50, 59), \DbSelect::in($v51 = rand(300, 399), null, $v52 = rand(400, 499)));
        $adapter->query($select);
        $this->assertInstanceOf('TestSTMT', $stmt = array_pop($pdo->statements));
        $this->assertQuery("SELECT t.* FROM `$tbl` t WHERE t.`$k1` IN (:w0, :w1) AND t.`$k2` IN (:w2, :w3)" .
                            " AND t.`$k3` IS NULL AND t.`$k4` IS NULL AND (t.`$k5` IS NULL OR (t.`$k5` IN (:w4, :w5))",
                            $stmt->sql);
        $this->assertEquals(array(':w0' => $v11, ':w1' => $v12, ':w2' => $v21, ':w3' => $v22, ':w4' => $v51,
                                  ':w5' => $v52), $stmt->params);
        $this->assertEquals(PDO::FETCH_ASSOC, $stmt->fetch_all);

        // try non-scalar
        $select->where($k6 = 'key' . rand(60, 69), \DbSelect::in(array(), 0, -1));
        try
        {
            $adapter->query($select);
            $this->fail();
        }
        catch (\Exception $e)
        {
            $this->assertEquals(sprintf(\DbSql::ERROR_KEY_NOT_SCALAR, $k6), $e->getMessage());
        }
    }

    public function testQueryNotIn()
    {
        $pdo = new TestPDO();
        $adapter = new DbSql($pdo);
        $select = new \DbSelect($tbl = 'tbl' . rand(100, 999));
        $select->where($k1 = 'key' . rand(10, 19), \DbSelect::not_in($v11 = rand(100, 199), $v12 = rand(200, 299)));
        $adapter->query($select);
        $this->assertInstanceOf('TestSTMT', $stmt = array_pop($pdo->statements));
        $this->assertQuery("SELECT t.* FROM `$tbl` t WHERE t.`$k1` NOT IN (:w0, :w1)", $stmt->sql);
        $this->assertEquals(array(':w0' => $v11, ':w1' => $v12), $stmt->params);
        $this->assertEquals(PDO::FETCH_ASSOC, $stmt->fetch_all);

        // empty NOT IN array
        $select->where($k2 = 'key' . rand(20, 29), \DbSelect::not_in());
        $adapter->query($select);
        $this->assertInstanceOf('TestSTMT', $stmt = array_pop($pdo->statements));
        $this->assertQuery("SELECT t.* FROM `$tbl` t WHERE t.`$k1` NOT IN (:w0, :w1) AND t.`$k2` IS NOT NULL",
                            $stmt->sql);
        $this->assertEquals(array(':w0' => $v11, ':w1' => $v12), $stmt->params);
        $this->assertEquals(PDO::FETCH_ASSOC, $stmt->fetch_all);

        // NOT IN with only NULL
        $select->where($k3 = 'key' . rand(30, 39), \DbSelect::not_in(null));
        $adapter->query($select);
        $this->assertInstanceOf('TestSTMT', $stmt = array_pop($pdo->statements));
        $this->assertQuery("SELECT t.* FROM `$tbl` t WHERE t.`$k1` NOT IN (:w0, :w1) AND t.`$k2` IS NOT NULL" .
                            " AND t.`$k3` IS NOT NULL", $stmt->sql);
        $this->assertEquals(array(':w0' => $v11, ':w1' => $v12), $stmt->params);
        $this->assertEquals(PDO::FETCH_ASSOC, $stmt->fetch_all);

        // array with NULL element
        $select->where($k4 = 'key' . rand(40, 49), \DbSelect::not_in($v41 = rand(100, 199), null, $v42 = rand(200, 299)));
        $adapter->query($select);
        $this->assertInstanceOf('TestSTMT', $stmt = array_pop($pdo->statements));
        $this->assertQuery("SELECT t.* FROM `$tbl` t WHERE t.`$k1` NOT IN (:w0, :w1) AND t.`$k2` IS NOT NULL" .
                            " AND t.`$k3` IS NOT NULL AND t.`$k4` IS NOT NULL AND t.`$k4` NOT IN (:w2, :w3)",
                            $stmt->sql);
        $this->assertEquals(array(':w0' => $v11, ':w1' => $v12, ':w2' => $v41, ':w3' => $v42), $stmt->params);
        $this->assertEquals(PDO::FETCH_ASSOC, $stmt->fetch_all);

        // try non-scalar
        $select->where($k5 = 'key' . rand(40, 49), \DbSelect::not_in(rand(100, 199), array(), rand(200, 299)));
        try
        {
            $adapter->query($select);
            $this->fail();
        }
        catch (\Exception $e)
        {
            $this->assertEquals(sprintf(\DbSql::ERROR_KEY_NOT_SCALAR, $k5), $e->getMessage());
        }
    }

    public function testQueryBetween()
    {
        $pdo = new TestPDO();
        $adapter = new DbSql($pdo);
        $select = new \DbSelect($tbl = 'tbl' . rand(100, 999));
        $select->where($k1 = 'key' . rand(10, 19), \DbSelect::between($v1 = rand(100, 400), $v2 = rand(500, 999)));
        $adapter->query($select);
        $this->assertInstanceOf('TestSTMT', $stmt = array_pop($pdo->statements));
        $this->assertQuery("SELECT t.* FROM `$tbl` t WHERE t.`$k1` BETWEEN :w0 AND :w1", $stmt->sql);
        $this->assertEquals(array(':w0' => $v1, ':w1' => $v2), $stmt->params);
        $this->assertEquals(PDO::FETCH_ASSOC, $stmt->fetch_all);

        // try first not scalar
        try
        {
            $select = new \DbSelect($tbl = 'tbl' . rand(100, 999));
            $select->where($k1, \DbSelect::between(array(), $v2 = rand(500, 999)));
            $adapter->query($select);
            $this->fail();
        }
        catch (\Exception $e)
        {
            $this->assertEquals(sprintf(\DbSql::ERROR_KEY_NOT_SCALAR, $k1), $e->getMessage());
        }

        // try 2nd not scalar
        try
        {
            $select = new \DbSelect($tbl = 'tbl' . rand(100, 999));
            $select->where($k1, \DbSelect::between($v2 = rand(500, 999), array()));
            $adapter->query($select);
            $this->fail();
        }
        catch (\Exception $e)
        {
            $this->assertEquals(sprintf(\DbSql::ERROR_KEY_NOT_SCALAR, $k1), $e->getMessage());
        }
    }

    public function testQueryIsNull()
    {
        $pdo = new TestPDO();
        $adapter = new DbSql($pdo);

        // try explicit is_null() function
        $select = new \DbSelect($tbl = 'tbl' . rand(100, 999));
        $select->where($k1 = 'key' . rand(10, 19), \DbSelect::is_null());
        $adapter->query($select);
        $this->assertInstanceOf('TestSTMT', $stmt = array_pop($pdo->statements));
        $this->assertQuery("SELECT t.* FROM `$tbl` t WHERE t.`$k1` IS NULL", $stmt->sql);
        $this->assertEquals(array(), $stmt->params);
        $this->assertEquals(PDO::FETCH_ASSOC, $stmt->fetch_all);

        // try empty array via in()
        $select->where($k2 = 'key' . rand(20, 29), \DbSelect::in());
        $adapter->query($select);
        $this->assertInstanceOf('TestSTMT', $stmt = array_pop($pdo->statements));
        $this->assertQuery("SELECT t.* FROM `$tbl` t WHERE t.`$k1` IS NULL AND t.`$k2` IS NULL", $stmt->sql);
        $this->assertEquals(array(), $stmt->params);
        $this->assertEquals(PDO::FETCH_ASSOC, $stmt->fetch_all);

        // try array with NULL only
        $select->where($k3 = 'key' . rand(30, 39), \DbSelect::in(null));
        $adapter->query($select);
        $this->assertInstanceOf('TestSTMT', $stmt = array_pop($pdo->statements));
        $this->assertQuery("SELECT t.* FROM `$tbl` t WHERE t.`$k1` IS NULL AND t.`$k2` IS NULL AND t.`$k3` IS NULL",
                            $stmt->sql);
        $this->assertEquals(array(), $stmt->params);
        $this->assertEquals(PDO::FETCH_ASSOC, $stmt->fetch_all);

        // try just empty array
        $select->where($k4 = 'key' . rand(40, 49), array());
        $adapter->query($select);
        $this->assertInstanceOf('TestSTMT', $stmt = array_pop($pdo->statements));
        $this->assertQuery("SELECT t.* FROM `$tbl` t WHERE t.`$k1` IS NULL AND t.`$k2` IS NULL AND t.`$k3` IS NULL" .
                            " AND t.`$k4` IS NULL", $stmt->sql);
        $this->assertEquals(array(), $stmt->params);
        $this->assertEquals(PDO::FETCH_ASSOC, $stmt->fetch_all);
    }

    public function testQueryNotNull()
    {
        $pdo = new TestPDO();
        $adapter = new DbSql($pdo);

        // try explicit not_null() function
        $select = new \DbSelect($tbl = 'tbl' . rand(100, 999));
        $select->where($k1 = 'key' . rand(10, 19), \DbSelect::not_null());
        $adapter->query($select);
        $this->assertInstanceOf('TestSTMT', $stmt = array_pop($pdo->statements));
        $this->assertQuery("SELECT t.* FROM `$tbl` t WHERE t.`$k1` IS NOT NULL", $stmt->sql);
        $this->assertEquals(array(), $stmt->params);
        $this->assertEquals(PDO::FETCH_ASSOC, $stmt->fetch_all);

        // try empty array via not_in()
        $select->where($k2 = 'key' . rand(20, 29), \DbSelect::not_in());
        $adapter->query($select);
        $this->assertInstanceOf('TestSTMT', $stmt = array_pop($pdo->statements));
        $this->assertQuery("SELECT t.* FROM `$tbl` t WHERE t.`$k1` IS NOT NULL AND t.`$k2` IS NOT NULL", $stmt->sql);
        $this->assertEquals(array(), $stmt->params);
        $this->assertEquals(PDO::FETCH_ASSOC, $stmt->fetch_all);

        // try array with NULL only
        $select->where($k3 = 'key' . rand(30, 39), \DbSelect::not_in(null));
        $adapter->query($select);
        $this->assertInstanceOf('TestSTMT', $stmt = array_pop($pdo->statements));
        $this->assertQuery("SELECT t.* FROM `$tbl` t WHERE t.`$k1` IS NOT NULL AND t.`$k2` IS NOT NULL" .
                            " AND t.`$k3` IS NOT NULL",
                            $stmt->sql);
        $this->assertEquals(array(), $stmt->params);
        $this->assertEquals(PDO::FETCH_ASSOC, $stmt->fetch_all);
    }

    /**
     * This tests various combinations of WHERE clause
     */
    public function testQueryWhere()
    {
        $pdo = new TestPDO();
        $adapter = new DbSql($pdo);
        $this->assertCount(0, $pdo->statements);

        // try whereless select
        $select = new \DbSelect($tbl = 'tbl' . rand(100, 999));
        $adapter->query($select);
        $this->assertInstanceOf('TestSTMT', $stmt = array_pop($pdo->statements));
        $this->assertQuery("SELECT t.* FROM `$tbl` t", $stmt->sql);
        $this->assertEquals(array(), $stmt->params);
        $this->assertEquals(PDO::FETCH_ASSOC, $stmt->fetch_all);

        // add pure where
        $select->where($k1 = 'key' . rand(10, 19), $v1 = rand(100, 199));
        $adapter->query($select);
        $this->assertInstanceOf('TestSTMT', $stmt = array_pop($pdo->statements));
        $this->assertQuery("SELECT t.* FROM `$tbl` t WHERE t.`$k1` = :w0", $stmt->sql);
        $this->assertEquals(array(':w0' => $v1), $stmt->params);
        $this->assertEquals(PDO::FETCH_ASSOC, $stmt->fetch_all);

        // add WHERE OR
        $select->whereOr($k2 = 'key' . rand(20, 29), \DbSelect::ne($v2 = rand(200, 299)));
        $adapter->query($select);
        $this->assertInstanceOf('TestSTMT', $stmt = array_pop($pdo->statements));
        $this->assertQuery("SELECT t.* FROM `$tbl` t WHERE (t.`$k1` = :w0 OR t.`$k2` != :w1)", $stmt->sql);
        $this->assertEquals(array(':w0' => $v1, ':w1' => $v2), $stmt->params);
        $this->assertEquals(PDO::FETCH_ASSOC, $stmt->fetch_all);

        // another WHERE OR
        $select->whereOr($k3 = 'key' . rand(30, 39), \DbSelect::lt($v3 = rand(300, 399)));
        $adapter->query($select);
        $this->assertInstanceOf('TestSTMT', $stmt = array_pop($pdo->statements));
        $this->assertQuery("SELECT t.* FROM `$tbl` t WHERE (t.`$k1` = :w0 OR t.`$k2` != :w1 OR t.`$k3` < :w2)",
                            $stmt->sql);
        $this->assertEquals(array(':w0' => $v1, ':w1' => $v2, ':w2' => $v3), $stmt->params);
        $this->assertEquals(PDO::FETCH_ASSOC, $stmt->fetch_all);

        // add full WHERE
        $select->where($k4 = 'key' . rand(40, 49), \DbSelect::gt($v4 = rand(400, 499)));
        $adapter->query($select);
        $this->assertInstanceOf('TestSTMT', $stmt = array_pop($pdo->statements));
        $this->assertQuery("SELECT t.* FROM `$tbl` t WHERE (t.`$k1` = :w0 OR t.`$k2` != :w1 OR t.`$k3` < :w2)" .
                            " AND t.`$k4` > :w3",
                            $stmt->sql);
        $this->assertEquals(array(':w0' => $v1, ':w1' => $v2, ':w2' => $v3, ':w3' => $v4), $stmt->params);
        $this->assertEquals(PDO::FETCH_ASSOC, $stmt->fetch_all);

        // another where or
        $select->whereOr($k5 = 'key' . rand(50, 59), \DbSelect::gte($v5 = rand(500, 599)));
        $adapter->query($select);
        $this->assertInstanceOf('TestSTMT', $stmt = array_pop($pdo->statements));
        $this->assertQuery("SELECT t.* FROM `$tbl` t WHERE (t.`$k1` = :w0 OR t.`$k2` != :w1 OR t.`$k3` < :w2) AND " .
                            "(t.`$k4` > :w3 OR t.`$k5` >= :w4)",
                            $stmt->sql);
        $this->assertEquals(array(':w0' => $v1, ':w1' => $v2, ':w2' => $v3, ':w3' => $v4, ':w4' => $v5), $stmt->params);
        $this->assertEquals(PDO::FETCH_ASSOC, $stmt->fetch_all);

        // and another where or
        $select->whereOr($k6 = 'key' . rand(60, 69), \DbSelect::is_null());
        $adapter->query($select);
        $this->assertInstanceOf('TestSTMT', $stmt = array_pop($pdo->statements));
        $this->assertQuery("SELECT t.* FROM `$tbl` t WHERE (t.`$k1` = :w0 OR t.`$k2` != :w1 OR t.`$k3` < :w2) AND " .
                            "(t.`$k4` > :w3 OR t.`$k5` >= :w4 OR t.`$k6` IS NULL)",
                            $stmt->sql);
        $this->assertEquals(array(':w0' => $v1, ':w1' => $v2, ':w2' => $v3, ':w3' => $v4, ':w4' => $v5), $stmt->params);
        $this->assertEquals(PDO::FETCH_ASSOC, $stmt->fetch_all);

        // another straight where
        $select->where($k7 = 'key' . rand(70, 79), \DbSelect::not_null());
        $adapter->query($select);
        $this->assertInstanceOf('TestSTMT', $stmt = array_pop($pdo->statements));
        $this->assertQuery("SELECT t.* FROM `$tbl` t WHERE (t.`$k1` = :w0 OR t.`$k2` != :w1 OR t.`$k3` < :w2) AND " .
                            "(t.`$k4` > :w3 OR t.`$k5` >= :w4 OR t.`$k6` IS NULL) AND t.`$k7` IS NOT NULL",
                            $stmt->sql);
        $this->assertEquals(array(':w0' => $v1, ':w1' => $v2, ':w2' => $v3, ':w3' => $v4, ':w4' => $v5), $stmt->params);
        $this->assertEquals(PDO::FETCH_ASSOC, $stmt->fetch_all);

        // and let's add an array - just for curiosity
        $select->whereOr($k8 = 'key' . rand(80, 89), array($v81 = rand(800, 849), null, $v82 = rand(850, 859)));
        $adapter->query($select);
        $this->assertInstanceOf('TestSTMT', $stmt = array_pop($pdo->statements));
        $this->assertQuery("SELECT t.* FROM `$tbl` t WHERE (t.`$k1` = :w0 OR t.`$k2` != :w1 OR t.`$k3` < :w2) AND " .
                            "(t.`$k4` > :w3 OR t.`$k5` >= :w4 OR t.`$k6` IS NULL) AND (t.`$k7` IS NOT NULL" .
                            " OR (t.`$k8` IS NULL OR (t.`$k8` IN (:w5, :w6)))",
                            $stmt->sql);
        $this->assertEquals(array(':w0' => $v1, ':w1' => $v2, ':w2' => $v3, ':w3' => $v4, ':w4' => $v5, ':w5' => $v81,
                                  ':w6' => $v82),
                            $stmt->params);
        $this->assertEquals(PDO::FETCH_ASSOC, $stmt->fetch_all);

        // ... and another WHERE for a clear conscience
        $select->where($k9 = 'key' . rand(90, 99), \DbSelect::like($v9 = $this->randValue()));
        $adapter->query($select);
        $this->assertInstanceOf('TestSTMT', $stmt = array_pop($pdo->statements));
        $this->assertQuery("SELECT t.* FROM `$tbl` t WHERE (t.`$k1` = :w0 OR t.`$k2` != :w1 OR t.`$k3` < :w2) AND " .
                            "(t.`$k4` > :w3 OR t.`$k5` >= :w4 OR t.`$k6` IS NULL) AND (t.`$k7` IS NOT NULL" .
                            " OR (t.`$k8` IS NULL OR (t.`$k8` IN (:w5, :w6))) AND t.`$k9` LIKE :w7",
                            $stmt->sql);
        $this->assertEquals(array(':w0' => $v1, ':w1' => $v2, ':w2' => $v3, ':w3' => $v4, ':w4' => $v5, ':w5' => $v81,
                                  ':w6' => $v82, ':w7' => $v9),
                            $stmt->params);
        $this->assertEquals(PDO::FETCH_ASSOC, $stmt->fetch_all);
    }

    public function testQueryOrder()
    {
        $pdo = new TestPDO();
        $adapter = new DbSql($pdo);
        $select = new \DbSelect($tbl = 'tbl' . rand(100, 999));
        $select->setOrder($order = 'o' . rand(100, 999));
        $adapter->query($select);
        $this->assertInstanceOf('TestSTMT', $stmt = array_pop($pdo->statements));
        $this->assertQuery("SELECT t.* FROM `$tbl` t ORDER BY `$order` ASC", $stmt->sql);
        $this->assertEquals(array(), $stmt->params);
        $this->assertEquals(PDO::FETCH_ASSOC, $stmt->fetch_all);

        // asc
        $select->setOrder("$order ASC");
        $adapter->query($select);
        $this->assertInstanceOf('TestSTMT', $stmt = array_pop($pdo->statements));
        $this->assertQuery("SELECT t.* FROM `$tbl` t ORDER BY `$order` ASC", $stmt->sql);
        $this->assertEquals(array(), $stmt->params);
        $this->assertEquals(PDO::FETCH_ASSOC, $stmt->fetch_all);

        // desc
        $select->setOrder("$order DESC");
        $adapter->query($select);
        $this->assertInstanceOf('TestSTMT', $stmt = array_pop($pdo->statements));
        $this->assertQuery("SELECT t.* FROM `$tbl` t ORDER BY `$order` DESC", $stmt->sql);
        $this->assertEquals(array(), $stmt->params);
        $this->assertEquals(PDO::FETCH_ASSOC, $stmt->fetch_all);

        // with quotations
        $select->setOrder("`$order` DESC");
        $adapter->query($select);
        $this->assertInstanceOf('TestSTMT', $stmt = array_pop($pdo->statements));
        $this->assertQuery("SELECT t.* FROM `$tbl` t ORDER BY `$order` DESC", $stmt->sql);
        $this->assertEquals(array(), $stmt->params);
        $this->assertEquals(PDO::FETCH_ASSOC, $stmt->fetch_all);

        // couple of orders
        $order2 = 'x' . rand(100, 999);
        $select->setOrder("$order, `$order2` DESC");
        $adapter->query($select);
        $this->assertInstanceOf('TestSTMT', $stmt = array_pop($pdo->statements));
        $this->assertQuery("SELECT t.* FROM `$tbl` t ORDER BY `$order` ASC, `$order2` DESC", $stmt->sql);
        $this->assertEquals(array(), $stmt->params);
        $this->assertEquals(PDO::FETCH_ASSOC, $stmt->fetch_all);
    }

    public function testQuerySearchLimit()
    {
        $pdo = new TestPDO();
        $adapter = new DbSql($pdo);
        $select = new \DbSelect($tbl = 'tbl' . rand(100, 999));
        $select->setSearchLimit($limit = rand(10, 99));
        $adapter->query($select);
        $this->assertInstanceOf('TestSTMT', $stmt = array_pop($pdo->statements));
        $this->assertQuery("SELECT t.* FROM `$tbl` t LIMIT $limit", $stmt->sql);
        $this->assertEquals(array(), $stmt->params);
        $this->assertEquals(PDO::FETCH_ASSOC, $stmt->fetch_all);

        $select->setSearchLimit($limit = rand(50, 99), $offset = rand(10, 49));
        $adapter->query($select);
        $this->assertInstanceOf('TestSTMT', $stmt = array_pop($pdo->statements));
        $this->assertQuery("SELECT t.* FROM `$tbl` t LIMIT $offset, $limit", $stmt->sql);
        $this->assertEquals(array(), $stmt->params);
        $this->assertEquals(PDO::FETCH_ASSOC, $stmt->fetch_all);
    }

    public function testQueryJoins()
    {
        $pdo = new TestPDO();
        $adapter = new DbSql($pdo);
        $select = new \DbSelect($tbl = 'tbl' . rand(100, 299));

        // join on table with constant
        $select->join($jtbl1 = 'tbl' . rand(300, 399), array($k1 = 'k' . rand(10, 19) => $v1 = $this->randValue()));
        $adapter->query($select);
        $this->assertInstanceOf('TestSTMT', $stmt = array_pop($pdo->statements));
        $this->assertQuery("SELECT t.* FROM `$tbl` t INNER JOIN `$jtbl1` j0 ON j0.`$k1` = :w0", $stmt->sql);
        $this->assertEquals(array(':w0' => $v1), $stmt->params);

        // join with main table and other condition
        $select->join($jtbl2 = 'tbl' . rand(400, 499), array($k2 = 'k' . rand(20, 29) => array($jtbl1, $k1),
                                                             $k3 = 'k' . rand(30, 39) => $v2 = $this->randValue()));
        $adapter->query($select);
        $this->assertInstanceOf('TestSTMT', $stmt = array_pop($pdo->statements));
        $this->assertQuery("SELECT t.* FROM `$tbl` t INNER JOIN `$jtbl1` j0 ON j0.`$k1` = :w0" .
                           " INNER JOIN `$jtbl2` j1 ON j1.`$k2` = $jtbl1.`$k1` AND j1.`$k3` = :w1",
                           $stmt->sql);
        $this->assertEquals(array(':w0' => $v1, ':w1' => $v2), $stmt->params);

        // try same table with an alias, but with columns
        $select->join(array($jtbl2, $a1 = 'a' . rand(10, 99)), array($k4 = 'k' . rand(40, 49) => array($jtbl1, $k1)));
        $adapter->query($select);
        $this->assertInstanceOf('TestSTMT', $stmt = array_pop($pdo->statements));
        $this->assertQuery("SELECT t.* FROM `$tbl` t INNER JOIN `$jtbl1` j0 ON j0.`$k1` = :w0" .
                           " INNER JOIN `$jtbl2` j1 ON j1.`$k2` = $jtbl1.`$k1` AND j1.`$k3` = :w1" .
                           " INNER JOIN `$jtbl2` $a1 ON $a1.`$k4` = $jtbl1.`$k1`",
                           $stmt->sql);
        $this->assertEquals(array(':w0' => $v1, ':w1' => $v2), $stmt->params);

        // add where -- see what happens
        $select->where($k5 = 'k' . rand(50, 59), $v3 = $this->randValue());
        $adapter->query($select);
        $this->assertInstanceOf('TestSTMT', $stmt = array_pop($pdo->statements));
        $this->assertQuery("SELECT t.* FROM `$tbl` t INNER JOIN `$jtbl1` j0 ON j0.`$k1` = :w0" .
                           " INNER JOIN `$jtbl2` j1 ON j1.`$k2` = $jtbl1.`$k1` AND j1.`$k3` = :w1" .
                           " INNER JOIN `$jtbl2` $a1 ON $a1.`$k4` = $jtbl1.`$k1`" .
                           " WHERE t.`$k5` = :w2",
                           $stmt->sql);
        $this->assertEquals(array(':w0' => $v1, ':w1' => $v2, ':w2' => $v3), $stmt->params);

        // add left join
        $select->joinLeft($jtbl2, array($k6 = 'k' . rand(60, 69) => array($jtbl1, $k1)), array('x1', 'x2' => 'x3'));
        $adapter->query($select);
        $this->assertInstanceOf('TestSTMT', $stmt = array_pop($pdo->statements));
        $this->assertQuery("SELECT t.*, j3.`x1`, j3.`x2` `x3` FROM `$tbl` t INNER JOIN `$jtbl1` j0 ON j0.`$k1` = :w0" .
                           " INNER JOIN `$jtbl2` j1 ON j1.`$k2` = $jtbl1.`$k1` AND j1.`$k3` = :w1" .
                           " INNER JOIN `$jtbl2` $a1 ON $a1.`$k4` = $jtbl1.`$k1`" .
                           "  LEFT JOIN `$jtbl2` j3 ON j3.`$k6` = $jtbl1.`$k1`" .
                           " WHERE t.`$k5` = :w2",
                           $stmt->sql);
        $this->assertEquals(array(':w0' => $v1, ':w1' => $v2, ':w2' => $v3), $stmt->params);


    }

}


