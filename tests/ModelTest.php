<?php
/*
 * @author Felix A. Milovanov
 */
require_once(__DIR__ . '/Abstract.php');
require_once(__DIR__ . '/models/Model/SimpleModel.php');
require_once(__DIR__ . '/models/Model/ComplexModel.php');
require_once(__DIR__ . '/models/Model/FriendModel.php');
require_once(__DIR__ . '/models/Mapper/SimpleModel.php');
require_once(__DIR__ . '/models/Mapper/ComplexModel.php');
require_once(__DIR__ . '/models/Mapper/FriendModel.php');

class Test_Model extends Test_Abstract
{
    public function testGetMapper()
    {
        $model = new Model_SimpleModel();
        $this->assertTrue($model->getMapper() instanceof Mapper_SimpleModel);
//
//
//        print_r(Model_SimpleModel::$__friends);
//
//        $model = new Model_ComplexModel();
//        print_r(Model_ComplexModel::$__friends);
    }

    public function testConstruct()
    {
        // try default
        $model = new Model_SimpleModel();
        $this->assertNull($model->getId());
        $this->assertEquals(Model_SimpleModel::X1_DEFAULT, $model->getX1());
        $this->assertEquals(Model_SimpleModel::X2_DEFAULT, $model->getX2());
        $this->assertFalse($model->isSearchPattern());

        // try search pattern
        $model = new Model_SimpleModel(false);
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
        $model = new Model_SimpleModel($val);
        $this->assertEquals($val['id'], $model->getId());
        $this->assertEquals(trim($val['x1']), $model->getX1()); // make sure it trims, e.g. calls setter
        $this->assertEquals(trim($val['x2']), $model->getX2()); // make sure it trims, e.g. calls setter
        $this->assertFalse($model->isSearchPattern());

        // try invalid values
        foreach (array(true, -1, 0, '', 'hello') as $val)
        {
            try
            {
                $model = new Model_SimpleModel($val);
                $this->fail();
            }
            catch (\InvalidArgumentException $e)
            {
            }
        }
    }

    public function testFriends()
    {
        // try simple model
        $val = array(
            'id'    => rand(100, 999),
            'x1'    => '  ' . $this->randValue() . ' ',
            'x2'    => ' ' . $this->randValue()
        );
        $model = new Model_SimpleModel($val);
        $this->assertEquals($val['id'], $model->getId());
        $this->assertEquals(trim($val['x1']), $model->getX1()); // make sure it trims, e.g. calls setter
        $this->assertEquals(trim($val['x2']), $model->getX2()); // make sure it trims, e.g. calls setter

        // create a class via mapper
        $mapper = Mapper_SimpleModel::getInstance();
        $model = $mapper->getModel($val);
        $this->assertEquals($val['id'], $model->getId());
        $this->assertEquals(trim($val['x1']), $model->getX1());
        $this->assertEquals(trim($val['x2']), $model->getX2());

        // create friend model
        $model = new Model_FriendModel($val);
        $this->assertEquals($val['id'], $model->getId());
        $this->assertEquals($val['x1'], $model->getX1()); // does not trim, e.g. bypasses setter
        $this->assertEquals($val['x2'], $model->getX2()); // does not trim, e.g. bypasses setter

        // create via mapper
        $mapper = Mapper_FriendModel::getInstance();
        $model = $mapper->getModel($val);
        $this->assertEquals($val['id'], $model->getId());
        $this->assertEquals($val['x1'], $model->getX1());
        $this->assertEquals($val['x2'], $model->getX2());
    }
}