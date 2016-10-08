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
        'x1'    => null
    );

    public function getX1() { return $this->_data['x1']; }
    public function setX1($val)
    {
        $this->_data['x1'] = trim($val);
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
            'x1'    => 'X1'
        );
    }
}

class TestAdapter implements IDbAdapter
{
    public $count = 1;
    public $inserts = array();
    public $updates = array();


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
        $this->assertEquals(array(array('my_table', array('x1' => $model->getX1()))), $db->inserts);

        // try to update
        $model->save();
        $this->assertCount(1, $db->inserts);
        $this->assertCount(1, $db->updates);
        $this->assertEquals(array(array('my_table', array('x1' => $model->getX1()), array('id' => $id))),
                            $db->updates);
    }
}