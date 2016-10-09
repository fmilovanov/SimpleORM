<?php
/*
 * @author Felix A. Milovanov
 */
require_once(__DIR__ . '/Abstract.php');
require_once(dirname(__DIR__) . '/SimpleORM/DbSelect.php');

class Test_DbSelect extends Test_Abstract
{
    public function testConstruct()
    {
        $select = new \DbSelect($tbl = 'table' . rand(100, 999));
        $this->assertEquals($tbl, $select->getTable());

        // try bad usecases
        foreach (array(null, 0, false, '123table', array('wow!')) as $tbl)
        {
            try
            {
                $select = new \DbSelect($tbl);
                $this->fail();
            }
            catch (\Exception $e)
            {
                $this->assertEquals(\DbSelect::ERROR_TABLE, $e->getMessage());
            }
        }
    }


    public function testWhere()
    {
        $select = new \DbSelect($table = 'tbl' . rand(100, 999));
        $this->assertCount(0, $select->getWhere());

        $select->where($k1 = 'key' . rand(10, 19), $v1 = $this->randValue());
        $this->assertCount(1, $where = $select->getWhere());
        $this->assertCount(1, $where[0]);
        $this->assertTrue($where[0][0] instanceof DbWhereCond);
        $this->assertEquals($k1, $where[0][0]->key);
        $this->assertEquals($v1, $where[0][0]->val1);
        $this->assertEquals(\DbSelect::OPERATOR_EQ, $where[0][0]->operator);

        // add another where
        $select->where($k21 = 'key' . rand(20, 29), $v21 = $this->randValue());
        $this->assertCount(2, $where = $select->getWhere());
        $this->assertCount(1, $where[0]);
        $this->assertCount(1, $where[1]);
        $this->assertTrue($where[1][0] instanceof DbWhereCond);
        $this->assertEquals($k21, $where[1][0]->key);
        $this->assertEquals($v21, $where[1][0]->val1);
        $this->assertEquals(\DbSelect::OPERATOR_EQ, $where[1][0]->operator);

        // add or where
        $select->whereOr($k22 = 'key' . rand(30, 39), $v22 = $this->randValue());
        $this->assertCount(2, $where = $select->getWhere());
        $this->assertCount(1, $where[0]);
        $this->assertCount(2, $where[1]);
        $this->assertTrue($where[1][0] instanceof DbWhereCond);
        $this->assertEquals($k21, $where[1][0]->key);
        $this->assertEquals($v21, $where[1][0]->val1);
        $this->assertEquals(\DbSelect::OPERATOR_EQ, $where[1][0]->operator);
        $this->assertTrue($where[1][1] instanceof DbWhereCond);
        $this->assertEquals($k22, $where[1][1]->key);
        $this->assertEquals($v22, $where[1][1]->val1);
        $this->assertEquals(\DbSelect::OPERATOR_EQ, $where[1][1]->operator);

        // add another or where, now an array
        $select->whereOr($k23 = 'key' . rand(40, 49), $v23 = array($this->randValue(), rand(1000, 9999)));
        $this->assertCount(2, $where = $select->getWhere());
        $this->assertCount(1, $where[0]);
        $this->assertCount(3, $where[1]);
        $this->assertTrue($where[1][2] instanceof DbWhereCond);
        $this->assertEquals($k23, $where[1][2]->key);
        $this->assertEquals($v23, $where[1][2]->val1);
        $this->assertEquals(\DbSelect::OPERATOR_IN, $where[1][2]->operator);

        // add another where
        $select->where($k31 = 'key' . rand(50, 59), $v31 = $this->randValue());
        $this->assertCount(3, $where = $select->getWhere());
        $this->assertCount(1, $where[2]);
        $this->assertTrue($where[2][0] instanceof DbWhereCond);
        $this->assertEquals($k31, $where[2][0]->key);
        $this->assertEquals($v31, $where[2][0]->val1);
        $this->assertEquals(\DbSelect::OPERATOR_EQ, $where[2][0]->operator);

        // add LT where OR
        $select->whereOr($k32 = 'key' . rand(60, 69), \DbSelect::lt($v32 = $this->randValue()));
        $this->assertCount(3, $where = $select->getWhere());
        $this->assertCount(2, $where[2]);
        $this->assertTrue($where[2][1] instanceof DbWhereCond);
        $this->assertEquals($k32, $where[2][1]->key);
        $this->assertEquals($v32, $where[2][1]->val1);
        $this->assertEquals(\DbSelect::OPERATOR_LT, $where[2][1]->operator);

        // LTE
        $select->whereOr($k33 = 'key' . rand(70, 79), \DbSelect::lte($v33 = $this->randValue()));
        $this->assertCount(3, $where = $select->getWhere());
        $this->assertCount(3, $where[2]);
        $this->assertTrue($where[2][2] instanceof DbWhereCond);
        $this->assertEquals($k33, $where[2][2]->key);
        $this->assertEquals($v33, $where[2][2]->val1);
        $this->assertEquals(\DbSelect::OPERATOR_LTE, $where[2][2]->operator);

        // GT
        $select->whereOr($k34 = 'key' . rand(80, 89), \DbSelect::gt($v34 = $this->randValue()));
        $this->assertCount(3, $where = $select->getWhere());
        $this->assertCount(4, $where[2]);
        $this->assertTrue($where[2][3] instanceof DbWhereCond);
        $this->assertEquals($k34, $where[2][3]->key);
        $this->assertEquals($v34, $where[2][3]->val1);
        $this->assertEquals(\DbSelect::OPERATOR_GT, $where[2][3]->operator);

        // GTE
        $select->whereOr($k35 = 'key' . rand(90, 99), \DbSelect::gte($v35 = $this->randValue()));
        $this->assertCount(3, $where = $select->getWhere());
        $this->assertCount(5, $where[2]);
        $this->assertTrue($where[2][4] instanceof DbWhereCond);
        $this->assertEquals($k35, $where[2][4]->key);
        $this->assertEquals($v35, $where[2][4]->val1);
        $this->assertEquals(\DbSelect::OPERATOR_GTE, $where[2][4]->operator);

        // IN as array
        $v41 = array(rand(10, 19), rand(20, 29));
        $select->where($k41 = 'key' . rand(100, 109), \DbSelect::in($v41));
        $this->assertCount(4, $where = $select->getWhere());
        $this->assertCount(1, $where[3]);
        $this->assertTrue($where[3][0] instanceof DbWhereCond);
        $this->assertEquals($k41, $where[3][0]->key);
        $this->assertEquals($v41, $where[3][0]->val1);
        $this->assertEquals(\DbSelect::OPERATOR_IN, $where[3][0]->operator);

        // IN as list of values
        $v421 = rand(40, 49);
        $v422 = rand(50, 59);
        $v423 = rand(60, 69);
        $select->whereOr($k42 = 'key' . rand(100, 109), \DbSelect::in($v421, $v422, $v423));
        $this->assertCount(4, $where = $select->getWhere());
        $this->assertCount(2, $where[3]);
        $this->assertTrue($where[3][1] instanceof DbWhereCond);
        $this->assertEquals($k42, $where[3][1]->key);
        $this->assertEquals(array($v421, $v422, $v423), $where[3][1]->val1);
        $this->assertEquals(\DbSelect::OPERATOR_IN, $where[3][1]->operator);

        // array of non-scalars
        try
        {
            $select->where('key', array(0, 1, array()));
            $this->fail();
        }
        catch (\Exception $e)
        {
            $this->assertEquals(\DbSelect::ERROR_NOT_SCALAR, $e->getMessage());
        }

        // missing "OR"
        $select = new \DbSelect($table);
        try
        {
            $select->whereOr('key', 1);
            $this->fail();
        }
        catch (\Exception $e)
        {
            $this->assertEquals(\DbSelect::ERROR_WHERE_OR, $e->getMessage());
        }

        // add normal where for or
        $select->where('key', 1);
        $select->whereOr('key', 2);

    }
}
