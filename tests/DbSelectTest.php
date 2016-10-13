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
        $this->assertNull($select->getColumns());

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

        // generate coumns
        $columns = array();
        for ($i = rand(3, 8); $i >= 0; $i--)
            $columns[] = 'x' . rand($i * 10, $i * 10 + 10);
        $select = new \DbSelect($tbl = 'table' . rand(100, 999), $columns);
        $this->assertEquals($tbl, $select->getTable());
        $this->assertEquals($columns, $select->getColumns());

        // try bad columns
        foreach (array(null, '', array(), false, '123x') as $column)
        {
            try
            {
                $select = new \DbSelect($tbl, array($column));
                $this->fail();
            }
            catch (\Exception $e)
            {
                $this->assertEquals(\DbSelect::ERROR_NOT_SCALAR, $e->getMessage());
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

        // NOT IN
        $v431 = rand(70, 79);
        $v432 = rand(80, 89);
        $v433 = rand(90, 99);
        $select->whereOr($k43 = 'key' . rand(110, 119), \DbSelect::not_in($v431, $v432, $v433));
        $this->assertCount(4, $where = $select->getWhere());
        $this->assertCount(3, $where[3]);
        $this->assertTrue($where[3][2] instanceof DbWhereCond);
        $this->assertEquals($k43, $where[3][2]->key);
        $this->assertEquals(array($v431, $v432, $v433), $where[3][2]->val1);
        $this->assertEquals(\DbSelect::OPERATOR_NOT_IN, $where[3][2]->operator);

        // another type of not in
        $select->whereOr($k44 = 'key' . rand(120, 129), \DbSelect::not_in(array($v431, $v432, $v433)));
        $this->assertCount(4, $where = $select->getWhere());
        $this->assertCount(4, $where[3]);
        $this->assertTrue($where[3][3] instanceof DbWhereCond);
        $this->assertEquals($k44, $where[3][3]->key);
        $this->assertEquals(array($v431, $v432, $v433), $where[3][3]->val1);
        $this->assertEquals(\DbSelect::OPERATOR_NOT_IN, $where[3][3]->operator);

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

    public function testWhereBetwwen()
    {
        $select = new \DbSelect($table = 'tbl' . rand(100, 999));
        $this->assertCount(0, $select->getWhere());

        $select->where($key = 'key' . rand(100, 999), \DbSelect::between($v1 = rand(100, 599), $v2 = rand(500, 999)));
        $this->assertCount(1, $where = $select->getWhere());
        $this->assertCount(1, $where[0]);
        $this->assertEquals($key, $where[0][0]->key);
        $this->assertEquals($v1, $where[0][0]->val1);
        $this->assertEquals($v2, $where[0][0]->val2);
        $this->assertEquals(\DbSelect::OPERATOR_BETWEEN, $where[0][0]->operator);
    }

    public function testWhereLike()
    {
        $select = new \DbSelect($table = 'tbl' . rand(100, 999));
        $this->assertCount(0, $select->getWhere());

        $select->where($key = 'key' . rand(100, 999), \DbSelect::like($v = $this->randValue()));
        $this->assertCount(1, $where = $select->getWhere());
        $this->assertCount(1, $where[0]);
        $this->assertEquals($key, $where[0][0]->key);
        $this->assertEquals($v, $where[0][0]->val1);
        $this->assertEquals(\DbSelect::OPERATOR_LIKE, $where[0][0]->operator);
    }


    public function testWhereIsNull()
    {
        $select = new \DbSelect($table = 'tbl' . rand(100, 999));
        $this->assertCount(0, $select->getWhere());

        $select->where($key = 'key' . rand(100, 999), \DbSelect::is_null());
        $this->assertCount(1, $where = $select->getWhere());
        $this->assertCount(1, $where[0]);
        $this->assertEquals($key, $where[0][0]->key);
        $this->assertEquals(array(), $where[0][0]->val1);
        $this->assertEquals(\DbSelect::OPERATOR_IN, $where[0][0]->operator);
    }

    public function testWhereNotNull()
    {
        $select = new \DbSelect($table = 'tbl' . rand(100, 999));
        $this->assertCount(0, $select->getWhere());

        $select->where($key = 'key' . rand(100, 999), \DbSelect::not_null());
        $this->assertCount(1, $where = $select->getWhere());
        $this->assertCount(1, $where[0]);
        $this->assertEquals($key, $where[0][0]->key);
        $this->assertEquals(array(), $where[0][0]->val1);
        $this->assertEquals(\DbSelect::OPERATOR_NOT_IN, $where[0][0]->operator);
    }

    public function testSetOrder()
    {
        $select = new \DbSelect($table = 'tbl' . rand(100, 999));

        $select->setOrder($order = 'o' . rand(100, 999));
        $this->assertEquals("`$order`", $select->getOrder());

        $select->setOrder("`$order`");
        $this->assertEquals("`$order`", $select->getOrder());

        $select->setOrder("`$order` ASC");
        $this->assertEquals("`$order` ASC", $select->getOrder());

        $select->setOrder("`$order` DESC");
        $this->assertEquals("`$order` DESC", $select->getOrder());

        $order2 = 'x' . rand(1000, 9999);
        $select->setOrder("`$order` DESC, $order2");
        $this->assertEquals("`$order` DESC, `$order2`", $select->getOrder());

        $select->setOrder("`$order`, `$order2` ASC");
        $this->assertEquals("`$order`, `$order2` ASC", $select->getOrder());
    }

    public function testJoin()
    {
        $select = new \DbSelect($table = 'tbl' . rand(100, 299));
        $this->assertCount(0, $select->getJoins());

        // add join
        $select->join($jtbl1 = 'tbl' . rand(100, 199), array($k1 = 'k' . rand(10, 19) => $v1 = rand(100, 199)));
        $this->assertCount(1, $joins = $select->getJoins());
        $this->assertArrayHasKey('j0', $joins);
        $join = $joins['j0'];
        $this->assertTrue($join instanceof \DbJoin);
        $this->assertEquals(\DbJoin::TYPE_INNER, $join->type);
        $this->assertEquals($jtbl1, $join->table);
        $this->assertCount(0, $join->columns);
        $this->assertCount(1, $join->on);
        $this->assertArrayHasKey($k1, $join->on);
        $this->assertEquals(new \DbWhereCond($k1, \DbSelect::OPERATOR_EQ, $v1), $join->on[$k1]);

        // add join to a table, aliased
        $select->joinLeft(array($jtbl2 = 'tbl' . rand(200, 299), $a2 = 'a' . rand(200, 299)),
                          array($k2 = 'k' . rand(20, 29) => array($jtbl1, $k1),
                                $k3 = 'k' . rand(30, 39) => \DbSelect::ne($v2 = rand(200, 299)),
                                $k4 = 'k' . rand(40, 49) => \DbSelect::lte($v3 = rand(300, 399))));
        $this->assertCount(2, $joins = $select->getJoins());
        $this->assertArrayHasKey($a2, $joins);
        $join = $joins[$a2];
        $this->assertEquals(\DbJoin::TYPE_LEFT, $join->type);
        $this->assertEquals($jtbl2, $join->table);
        $this->assertCount(0, $join->columns);
        $this->assertCount(3, $join->on);
        $this->assertEquals(array($jtbl1, $k1), $join->on[$k2]);
        $this->assertEquals(new \DbWhereCond($k3, \DbSelect::OPERATOR_NE, $v2), $join->on[$k3]);
        $this->assertEquals(new \DbWhereCond($k4, \DbSelect::OPERATOR_LTE, $v3), $join->on[$k4]);

        // add same table, another alias
        $select->joinLeft(array($jtbl2, $a3 = 'a' . rand(300, 399)), array($k1 => $this->randValue()));
        $this->assertCount(3, $joins = $select->getJoins());
        $this->assertArrayHasKey($a3, $joins);

        // try to use same alias
        try
        {
            $select->joinLeft(array($jtbl2, $a3), array($k1 => $this->randValue()));
            $this->fail();
        }
        catch (\Exception $e)
        {
            $this->assertEquals(\DbSelect::ERROR_JOINED, $e->getMessage());
            $this->assertCount(3, $joins = $select->getJoins());
        }

        // try to add inner join after left one
        try
        {
            $select->join('tbl' . rand(300, 399), array($k1 => $this->randValue()));
            $this->fail();
        }
        catch (\Exception $e)
        {
            $this->assertEquals(\DbSelect::ERROR_JOIN_LEFT, $e->getMessage());
            $this->assertCount(3, $joins = $select->getJoins());
        }

        // bad table name
        foreach (array(null, false, true, array(), '55a') as $val)
        {
            try
            {
                $select->joinLeft(array($val, 'a' . rand(400, 499)), array($k1 => $this->randValue()));
                $this->fail();
            }
            catch (\Exception $e)
            {
                $this->assertEquals(\DbSelect::ERROR_JOIN_TABLE, $e->getMessage());
                $this->assertCount(3, $joins = $select->getJoins());
            }
        }

        /// test columns clause
        $select = new \DbSelect($table);
        $select->join($jtbl1, array('k1' => rand(1009, 9999)), array('x1', 'x2'));
        $select->join($jtbl1, array('k1' => rand(1009, 9999)), array('x1' => 'a1', 'x2' => 'a2'));
    }

}
