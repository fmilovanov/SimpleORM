<?php
/*
 * @author Felix A. Milovanov
 */
require_once(__DIR__ . '/Abstract.php');
require_once(__DIR__ . '/models/Model/SimpleModel.php');
require_once(__DIR__ . '/models/Model/ComplexModel.php');
require_once(__DIR__ . '/models/Model/JoinModel.php');
require_once(__DIR__ . '/models/Model/OnlyUpdate.php');
require_once(__DIR__ . '/models/Model/OnlyInsert.php');
require_once(__DIR__ . '/models/Model/NoSave.php');
require_once(__DIR__ . '/models/Model/User.php');
require_once(__DIR__ . '/models/Mapper/SimpleModel.php');
require_once(__DIR__ . '/models/Mapper/ComplexModel.php');
require_once(__DIR__ . '/models/Mapper/JoinModel.php');
require_once(__DIR__ . '/models/Mapper/OnlyUpdate.php');
require_once(__DIR__ . '/models/Mapper/OnlyInsert.php');
require_once(__DIR__ . '/models/Mapper/NoSave.php');

class TestAdapter implements IDbAdapter
{
    public $count = 1;
    public $inserts = array();
    public $updates = array();
    public $deletes = array();
    public $selects = array();
    public $begins = 0;
    public $commits = 0;
    public $rollbacks = 0;

    public $data = array();

    public function insert($table, array $data)
    {
        $this->inserts[] = array($table, $data);
    }

    public function update($table, array $data, array $where)
    {
        $this->updates[] = array($table, $data, $where);
    }

    public function delete($table, array $where, $allow_delete_all = false)
    {
        $this->deletes[] = array($table, $where);
    }

    public function lastInsertId()
    {
        return $this->count++;
    }

    public function beginTransaction() { $this->begins += 1; }
    public function commit() { $this->commits += 1; }
    public function rollback() { $this->rollbacks += 1; }

    public function query(\DbSelect $select)
    {
        $this->selects[] = $select;
        return empty($this->data) ? array() : array(array_pop($this->data));
    }
}

class Test_Mapper extends Test_Abstract
{
    public function testGetTableName()
    {
        $mapper = Mapper_ComplexModel::getInstance();
        $this->assertEquals('complex_model', $mapper->getTableName());
    }

    public function testSave()
    {
        $db = new TestAdapter();
        $db->count = $id = rand(1000, 9999);
        Mapper::setDefaultDbAdapter($db);
        $this->assertCount(0, $db->inserts);

        $model = new Model_ComplexModel();
        $model->setX1($this->randValue());
        $model->setX2($this->randValue());
        $model->setCreatedOn($this->randValue());
        $model->setUpdatedOn($this->randValue());
        $this->assertFalse($model->validated);

        // try to insert
        $model->save();
        $this->assertEquals($id, $model->getId());
        $this->assertTrue($model->validated);
        $this->assertCount(0, $db->updates);
        $this->assertCount(1, $db->inserts);

        // check table/regural params
        $insert = array_pop($db->inserts);
        $this->assertEquals($model->getMapper()->getTableName(), $insert[0]);
        $this->assertEquals($model->getX1(), $insert[1]['x1']);
        $this->assertEquals($model->getX2(), $insert[1]['x2']);

        // check created/updated on
        $this->assertLessThan(1, time() - strtotime($insert[1]['created_on']));
        $this->assertLessThan(1, time() - strtotime($insert[1]['updated_on']));
        $this->assertEquals($insert[1]['created_on'], $model->getCreatedOn());
        $this->assertEquals($insert[1]['updated_on'], $model->getUpdatedOn());

        // try to update
        $model->setCreatedOn($this->randValue())->setUpdatedOn($this->randValue())->save();
        $this->assertCount(0, $db->inserts);
        $this->assertCount(1, $db->updates);

        // check table/regural params
        $update = array_pop($db->updates);
        $this->assertEquals($model->getMapper()->getTableName(), $update[0]);
        $this->assertEquals($model->getX1(), $update[1]['x1']);
        $this->assertEquals($model->getX2(), $update[1]['x2']);

        // check created/updated on
        $this->assertArrayNotHasKey('created_on', $update);
        $this->assertLessThan(1, time() - strtotime($insert[1]['updated_on']));
        $this->assertEquals($insert[1]['updated_on'], $model->getUpdatedOn());

        // check where clause
        $this->assertEquals(array('id' => $model->getId()), $update[2]);
    }

    public function testCreatedUpdatedBy()
    {
        $db = new TestAdapter();
        Mapper::setDefaultDbAdapter($db);

        $model = new \Model_ComplexModel();
        $model->setCreatedBy($cb = rand(100, 499));
        $model->setUpdatedBy($ub = rand(500, 599));
        $model->save();

        // check no user set
        $this->assertEquals($cb, $model->getCreatedBy());
        $this->assertEquals($ub, $model->getUpdatedBy());

        $insert = array_pop($db->inserts);
        $this->assertEquals($cb, $insert[1]['created_by']);
        $this->assertEquals($ub, $insert[1]['updated_by']);

        // create a user
        $user = new \Model_User();
        $user->setId($uid = rand(10, 59));
        \Mapper::setUser($user);

        // update model
        $model->setCreatedBy(rand(600, 699));
        $model->setUpdatedBy(rand(700, 799));
        $model->save();
        $this->assertNotEquals($cb, $model->getCreatedBy());
        $this->assertEquals($uid, $model->getUpdatedBy());

        $update = array_pop($db->updates);
        $this->assertEquals($uid, $update[1]['updated_by']);
        $this->assertArrayNotHasKey('created_by', $update[1]);

        // new model
        $model = new \Model_ComplexModel();
        $model->setCreatedBy($cb = rand(100, 499));
        $model->setUpdatedBy($ub = rand(500, 599));
        $model->save();

        $this->assertEquals($uid, $model->getCreatedBy());
        $this->assertEquals($uid, $model->getUpdatedBy());

        $insert = array_pop($db->inserts);
        $this->assertEquals($uid, $insert[1]['created_by']);
        $this->assertEquals($uid, $insert[1]['updated_by']);
    }

    public function testJoinOnSave()
    {
        $db = new TestAdapter();
        $db->data[] = array('id' => $db->count,  'status' => $status = $this->randValue());
        Mapper::setDefaultDbAdapter($db);

        $model = new Model_JoinModel();
        $model->setStatusId($status_id = rand(100, 999));

        // create new model and check
        $model->save();
        $this->assertCount(1, $db->selects);
        $select = array_pop($db->selects);
        $this->assertCount(1, $joins = $select->getJoins());
        $join = array_pop($joins);
        $this->assertEquals('statuses', $join->table);
        $this->assertEquals(array('id' => array($model->getMapper()->getTableName(), 'status_id')), $join->on);
        $this->assertEquals(array('name' => 'status'), $join->columns);
        $this->assertEquals($status, $model->getStatus());

        // update model
        $db->data[] = array('id' => $db->count,  'status' => $status2 = $this->randValue() . '.' . rand(100, 999));
        $model->save();
        $this->assertEquals($status2, $model->getStatus());
    }

    public function testDeleteNoDeletedOn()
    {
        $db = new TestAdapter();
        Mapper::setDefaultDbAdapter($db);

        $mapper = \Mapper_SimpleModel::getInstance();
        $model = new Model_SimpleModel();
        $model->setX1($this->randValue());

        // try to delete w/out save
        try
        {
            $model->delete();
            $this->fail();
        }
        catch (\Exception $e)
        {
            $this->assertEquals(\Mapper::ERROR_NO_ID, $e->getMessage());
            $this->assertCount(0, $db->deletes);
            $this->assertCount(0, $db->updates);
        }

        // try soft delete on non-soft-deleteable
        $model->save();
        $this->assertTrue($mapper->find($model->getId()) instanceof Model_SimpleModel);
        try
        {
            $model->delete();
            $this->fail();
        }
        catch (\Exception $e)
        {
            $this->assertEquals(\Mapper::ERROR_SOFT_DELETE, $e->getMessage());
            $this->assertCount(0, $db->deletes);
            $this->assertCount(0, $db->updates);
            $this->assertTrue($mapper->find($model->getId()) instanceof Model_SimpleModel);
        }

        // try hard delete
        $model->delete(true);
        $this->assertNull($mapper->find($model->getId()));
        $this->assertNull($mapper->find($model->getId()));
        $this->assertCount(0, $db->updates);
        $this->assertCount(1, $db->deletes);
        $this->assertEquals($mapper->getTableName(), $db->deletes[0][0]);
        $this->assertEquals(array('id' => $model->getId()), $db->deletes[0][1]);
    }

    public function testDeleteWithDeletedOn()
    {
        $db = new TestAdapter();
        Mapper::setDefaultDbAdapter($db);

        $mapper = \Mapper_ComplexModel::getInstance();
        $model = new Model_ComplexModel();
        $model->setX1($this->randValue());

        // try to delete w/out save
        try
        {
            $model->delete();
            $this->fail();
        }
        catch (\Exception $e)
        {
            $this->assertEquals(\Mapper::ERROR_NO_ID, $e->getMessage());
            $this->assertCount(0, $db->deletes);
            $this->assertCount(0, $db->updates);
        }

        // soft delete
        $model->save();
        $this->assertTrue($mapper->find($model->getId()) instanceof Model_ComplexModel);
        $this->assertNull($model->getDeletedOn());
        $model->delete();
        $this->assertLessThan(1, time() - strtotime($model->getDeletedOn()));
        $this->assertCount(0, $db->deletes);
        $this->assertCount(1, $db->updates);
        $this->assertNull($mapper->find($model->getId()));
        $this->assertEquals($mapper->getTableName(), $db->updates[0][0]);
        $this->assertEquals(array('deleted_on' => $model->getDeletedOn()), $db->updates[0][1]);
        $this->assertEquals(array('id' => $model->getId()), $db->updates[0][2]);

        // save again
        $model->setDeletedOn(null)->save();
        $this->assertCount(2, $db->updates);
        $this->assertTrue($mapper->find($model->getId()) instanceof Model_ComplexModel);

        // hard delete
        $model->delete(true);
        $this->assertCount(2, $db->updates);
        $this->assertCount(1, $db->deletes);
        $this->assertNull($mapper->find($model->getId()));
        $this->assertEquals($mapper->getTableName(), $db->deletes[0][0]);
        $this->assertEquals(array('id' => $model->getId()), $db->deletes[0][1]);
    }

    public function testSearch()
    {
        $db = new TestAdapter();
        Mapper::setDefaultDbAdapter($db);
        $this->assertCount(0, $db->selects);

        $model = new Model_ComplexModel(false);
        $model->search();
        $this->assertTrue(($select = array_pop($db->selects)) instanceof \DbSelect);
        $this->assertEquals($model->getMapper()->getTableName(), $select->getTable());
        $this->assertEquals(array(), $select->getWhere());

        // add search criteria
        $cond1 = new \DbWhereCond();
        $cond1->key = 'x1';
        $cond1->operator = \DbSelect::OPERATOR_EQ;
        $model->setX1($cond1->val1 = $this->randValue())->search();
        $this->assertTrue(($select = array_pop($db->selects)) instanceof \DbSelect);
        $this->assertEquals($model->getMapper()->getTableName(), $select->getTable());
        $this->assertEquals(array(array($cond1)), $select->getWhere());
        $this->assertNull($select->getOrder());

        // search criteria as array
        $cond2 = new \DbWhereCond();
        $cond2->key = 'x2';
        $cond2->operator = \DbSelect::OPERATOR_IN;
        $model->setX2($cond2->val1 = array($this->randValue(), rand(100, 999)))->search();
        $this->assertTrue(($select = array_pop($db->selects)) instanceof \DbSelect);
        $this->assertEquals($model->getMapper()->getTableName(), $select->getTable());
        $this->assertEquals(array(array($cond1), array($cond2)), $select->getWhere());
        $this->assertNull($select->getOrder());

        // add order
        $model->search($order = 'o' . rand(100, 999));
        $this->assertTrue(($select = array_pop($db->selects)) instanceof \DbSelect);
        $this->assertEquals($model->getMapper()->getTableName(), $select->getTable());
        $this->assertEquals(array(array($cond1), array($cond2)), $select->getWhere());
        $this->assertEquals("`$order`", $select->getOrder());
    }

    public function testCache()
    {
        $db = new TestAdapter();
        $db->count = $id = rand(10, 99);
        Mapper::setDefaultDbAdapter($db);

        $mapper = Mapper_SimpleModel::getInstance();
        $this->assertNull($mapper->find($id));

        // save an item
        $model = new Model_SimpleModel();
        $model->setX1($this->randValue());
        $model->setX2($this->randValue());
        $model->save();
        $this->assertTrue($mapper->find($id) instanceof Model_SimpleModel);

        // clear cache
        $mapper->clearCache();
        $this->assertNull($mapper->find($id));

        // save it again
        $model->save();
        $this->assertTrue($mapper->find($id) instanceof Model_SimpleModel);

        Mapper::clearAllCaches();
        $this->assertNull($mapper->find($id));
    }

    public function testTransactions()
    {
        $db = new TestAdapter();
        Mapper::setDefaultDbAdapter($db);

        $mapper1 = \Mapper_SimpleModel::getInstance();
        $mapper2 = \Mapper_ComplexModel::getInstance();
        $this->assertEquals(0, $db->begins);
        $this->assertEquals(0, $db->commits);
        $this->assertEquals(0, $db->rollbacks);

        // start transaction
        $tid = $mapper1->beginTransaction();
        $this->assertEquals(1, $db->begins);
        $this->assertEquals(0, $db->commits);
        $this->assertEquals(0, $db->rollbacks);

        // start another transaction
        $tid2 = $mapper1->beginTransaction();
        $this->assertEquals(1, $db->begins);
        $this->assertEquals(0, $db->commits);
        $this->assertEquals(0, $db->rollbacks);

        // start transaction with another mapper
        $tid3 = $mapper2->beginTransaction();
        $this->assertEquals(1, $db->begins);
        $this->assertEquals(0, $db->commits);
        $this->assertEquals(0, $db->rollbacks);

        // commit 3rd one
        $mapper2->commit($tid3);
        $this->assertEquals(1, $db->begins);
        $this->assertEquals(0, $db->commits);
        $this->assertEquals(0, $db->rollbacks);

        // commit 2nd one
        $mapper1->commit($tid2);
        $this->assertEquals(1, $db->begins);
        $this->assertEquals(0, $db->commits);
        $this->assertEquals(0, $db->rollbacks);

        // commit 1st one
        $mapper1->commit($tid);
        $this->assertEquals(1, $db->begins);
        $this->assertEquals(1, $db->commits);
        $this->assertEquals(0, $db->rollbacks);
    }

    public function testUpdateOnlyModel()
    {
        $db = new TestAdapter($pdo);
        Mapper::setDefaultDbAdapter($db);

        // try to insert a new item
        $model = new \Model_OnlyUpdate();
        try
        {
            $model->save();
            $this->fail();
        }
        catch (\Exception $e)
        {
            $this->assertEquals(Mapper::ERROR_NO_INSERT, $e->getMessage());
        }

        // fake an object
        $db->data = array(array('id' => $id = rand(10, 99), 'updated_on' => $this->randValue(),
                                'updated_by' => rand(10, 99)));
        $mapper = \Mapper_OnlyUpdate::getInstance();
        $this->assertNotNull($model = $mapper->find($id));

        // try to update it
        $model->save();
        $this->assertLessThan(2, time() - strtotime($model->getUpdatedOn()));
    }

    public function testInsertOnlyModel()
    {
        $db = new TestAdapter($pdo);
        Mapper::setDefaultDbAdapter($db);

        // try to insert a new item
        $model = new \Model_OnlyInsert();
        $model->save();
        $this->assertNotNull($model->getId());
        $this->assertNotNull($model->getUpdatedOn());
        return;

        // try to update
        try
        {
            $model->save();
            $this->fail();
        }
        catch (\Exception $e)
        {
            $this->assertEquals(Mapper::ERROR_NO_UPDATE, $e->getMessage());
        }
    }

    public function testNoSaveModel()
    {
        $db = new TestAdapter($pdo);
        Mapper::setDefaultDbAdapter($db);

        // try to insert a new item
        $model = new \Model_NoSave();
        try
        {
            $model->save();
            $this->fail();
        }
        catch (\Exception $e)
        {
            $this->assertEquals(Mapper::ERROR_NO_INSERT, $e->getMessage());
        }

        // fake an object
        $db->data = array(array('id' => $id = rand(10, 99), 'updated_on' => $this->randValue(),
                                'updated_by' => rand(10, 99)));
        $mapper = \Mapper_NoSave::getInstance();
        $this->assertNotNull($model = $mapper->find($id));

        // try to update
        try
        {
            $model->save();
            $this->fail();
        }
        catch (\Exception $e)
        {
            $this->assertEquals(Mapper::ERROR_NO_UPDATE, $e->getMessage());
        }
    }

}