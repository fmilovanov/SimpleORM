<?php
/*
 * @author Felix A. Milovanov
 */
require_once(__DIR__ . '/Abstract.php');

class Model_A1 extends Model
{
    const X1_DEFAULT    = '1';
    const X2_DEFAULT    = '1';

    protected static $__defaults = array(
        'id'    => null,
        'x1'    => self::X1_DEFAULT,
        'x2'    => self::X2_DEFAULT
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
        $this->_data['x2'] = trim($val);
        return $this;
    }
}

class Mapper_A1 extends Mapper
{
    public function getColumns()
    {
        return array(
            'x1'    => 'X1',
            'x2'    => 'X2'
        );
    }
}

class Test_Model extends Test_Abstract
{
    public function testGetMapper()
    {
        $model = new Model_A1();
        $this->assertTrue($model->getMapper() instanceof Mapper_A1);
    }

    public function testConstruct()
    {
        // try default
        $model = new Model_A1();
        $this->assertNull($model->getId());
        $this->assertEquals(Model_A1::X1_DEFAULT, $model->getX1());
        $this->assertEquals(Model_A1::X2_DEFAULT, $model->getX2());
        $this->assertFalse($model->isSearchPattern());

        // try search pattern
        $model = new Model_A1(false);
        $this->assertNull($model->getId());
        $this->assertNull($model->getX1());
        $this->assertNull($model->getX2());
        $this->assertTrue($model->isSearchPattern());

        // try array
        $val = array(
            'id'    => rand(100, 999),
            'x1'    => '  ' . $this->randValue() . ' ',
            'x2'    => ' ' . $this->randValue()
        );
        $model = new Model_A1($val);
        $this->assertEquals($val['id'], $model->getId());
        $this->assertEquals(trim($val['x1']), $model->getX1()); // make sure it trims, e.g. calls setter
        $this->assertEquals(trim($val['x2']), $model->getX2()); // make sure it trims, e.g. calls setter
        $this->assertFalse($model->isSearchPattern());

        // try invalid values
        foreach (array(true, -1, 0, '', 'hello') as $val)
        {
            try
            {
                $model = new Model_A1($val);
                $this->fail();
            }
            catch (\InvalidArgumentException $e)
            {
            }
        }

    }
}