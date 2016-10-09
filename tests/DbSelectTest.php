<?php
/*
 * @author Felix A. Milovanov
 */
require_once(__DIR__ . '/Abstract.php');
require_once(dirname(__DIR__) . '/SimpleORM/DbSelect.php');

class Test_DbSelect extends Test_Abstract
{
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
        $select->where($k3 = 'key' . rand(50, 59), $v3 = $this->randValue());
        $this->assertCount(3, $where = $select->getWhere());
        $this->assertCount(1, $where[2]);
        $this->assertTrue($where[2][0] instanceof DbWhereCond);
        $this->assertEquals($k3, $where[2][0]->key);
        $this->assertEquals($v3, $where[2][0]->val1);
        $this->assertEquals(\DbSelect::OPERATOR_EQ, $where[2][0]->operator);










    }
}
