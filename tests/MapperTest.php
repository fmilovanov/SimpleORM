<?php
/*
 * @author Felix A. Milovanov
 */
require_once(__DIR__ . '/Abstract.php');
require_once(__DIR__ . '/models/Model/SimpleModel.php');
require_once(__DIR__ . '/models/Model/ComplexModel.php');
require_once(__DIR__ . '/models/Mapper/SimpleModel.php');
require_once(__DIR__ . '/models/Mapper/ComplexModel.php');

class TestAdapter implements IDbAdapter
{
    public $count = 1;
    public $inserts = array();
    public $updates = array();
    public $deletes = array();
    public $selects = array();


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

    public function beginTransaction() { }
    public function commit() { }
    public function rollback() { }

    public function query(\DbSelect $select)
    {
        $this->selects[] = $select;
        return array();
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
}