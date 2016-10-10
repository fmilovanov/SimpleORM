<?php
/*
 * @author Felix A. Milovanov
 */
require_once(__DIR__ . '/Abstract.php');

class Model_MyTable extends Model
{
    public $validated   = false;

    protected static $__defaults = array(
        'id'    => null,
        'x1'    => null,
        'x2'    => null
    );

    public function getX1() { return $this->_data['x1']; }
    public function setX1($val)
    {
        $this->_data['x1'] = trim($val);
        return $this;
    }

    public function getX2() { return $this->_data['x2']; }
    public function setX2($val)
    {
        $this->_data['x2'] = $val;
        return $this;
    }

    public function validate()
    {
        $this->validated = true;
    }
}

class Mapper_MyTable extends Mapper
{
    public function getColumns() {
        return array(
            'x1'    => 'X1',
            'x2'    => 'X2'
        );
    }
}

class TestAdapter implements IDbAdapter
{
    public $count = 1;
    public $inserts = array();
    public $updates = array();
    public $selects = array();


    public function insert($table, array $data)
    {
        $this->inserts[] = array($table, $data);
    }

    public function update($table, array $data, array $where)
    {
        $this->updates[] = array($table, $data, $where);
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
        $mapper = Mapper_MyTable::getInstance();
        $this->assertEquals('my_table', $mapper->getTableName());
    }

    public function testSave()
    {
        $db = new TestAdapter();
        $db->count = $id = rand(1000, 9999);
        Mapper::setDefaultDbAdapter($db);
        $this->assertCount(0, $db->inserts);

        $model = new Model_MyTable();
        $model->setX1($this->randValue());
        $this->assertFalse($model->validated);

        // try to insert
        $model->save();
        $this->assertEquals($id, $model->getId());
        $this->assertTrue($model->validated);
        $this->assertCount(0, $db->updates);
        $this->assertCount(1, $db->inserts);
        $this->assertEquals(array(array('my_table', array('x1' => $model->getX1(), 'x2' => null))), $db->inserts);

        // try to update
        $model->save();
        $this->assertCount(1, $db->inserts);
        $this->assertCount(1, $db->updates);
        $this->assertEquals(array(array('my_table', array('x1' => $model->getX1(), 'x2' => null), array('id' => $id))),
                            $db->updates);
    }

    public function testSearch()
    {
        $db = new TestAdapter();
        Mapper::setDefaultDbAdapter($db);
        $this->assertCount(0, $db->selects);

        $model = new Model_MyTable();
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
        $model->search($order = $this->randValue());
        $this->assertTrue(($select = array_pop($db->selects)) instanceof \DbSelect);
        $this->assertEquals($model->getMapper()->getTableName(), $select->getTable());
        $this->assertEquals(array(array($cond1), array($cond2)), $select->getWhere());
        $this->assertEquals($order, $select->getOrder());
    }
}