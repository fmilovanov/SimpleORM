<?php
/*
 * @author Felix A. Milovanov
 */
require_once(__DIR__ . '/Abstract.php');
require_once(dirname(__DIR__) . '/SimpleORM/DbVirtual.php');
require_once(dirname(__DIR__) . '/SimpleORM/DbSelect.php');
require_once(dirname(__DIR__) . '/SimpleORM/DbException.php');

class Test_DbVirtual extends Test_Abstract
{
    public function testLastInsertId()
    {
        $db = new \DbVirtual();

        for ($i = 0; $i < 10; $i++)
        {
            $id = $db->last_id = rand(1000, 9999);
            $this->assertEquals($id, $db->lastInsertId());
        }
    }

    public function testInsert()
    {
        $db = new \DbVirtual();

        for ($i = 0; $i < 10; $i++)
        {
            $table = 'tbl' . rand($i * 100, $i * 100 + 99);
            $this->assertArrayNotHasKey($table, $db->tables);

            for ($j = 0; $j < 10; $j++)
            {
                $data = array();
                for ($k = rand(2, 9); $k >= 0; $k--)
                    $data["x$k"] = rand(100, 999);

                $db->insert($table, $data);
                $this->assertArrayHasKey($table, $db->tables);
                $this->assertArrayHasKey($db->lastInsertId(), $db->tables[$table]);

                $data['id'] = $db->lastInsertId();
                $this->assertEquals($data, $db->tables[$table][$db->lastInsertId()]);
            }
        }
    }

    protected function generateKeys($num_keys, $prefix = 'k')
    {
        $keys = array();
        for ($i = 0; $i <= $num_keys; $i++)
            $keys[] = $prefix . rand($i * 10, $i * 10 + 9);

        return $keys;
    }

    protected function generateData(array $keys, array $extra_data = array())
    {
        $data = array();
        foreach ($keys as $key)
            $data[$key] = $this->randValue ();

        foreach ($extra_data as $key => $value)
            $data[$key] = $value;

        return $data;
    }



    public function testUpdate()
    {
        $db = new \DbVirtual();
        $tables = array();

        for ($i = 0; $i < 10; $i++)
        {
            // generate table
            $table = 'tbl' . rand($i * 100, $i * 100 + 99);
            $tables[$table] = array();

            // generate keys
            $keys = $this->generateKeys(rand(4, 9));
            $mkey = 'k' . rand(100, 499);
            $mkey2 = 'k' . rand(500, 999);
            $mvalue = rand(1000, 9999);

            for ($j = 0; $j < 10; $j++)
            {
                $data = $this->generateData($keys, array($mkey => $mvalue, $mkey2 => $this->randValue()));

                // insert and check
                $db->insert($table, $data);
                $id = $data['id'] = $db->lastInsertId();
                $tables[$table][$id] = $data;
                $this->assertEquals($tables, $db->tables);

                // update single record by ID
                foreach ($keys as $key)
                    $data[$key] = $this->randValue();
                $tables[$table][$id] = $data;
                $db->update($table, $data, array('id' => $id));
                $this->assertEquals($tables, $db->tables);

                // update all records by mkey
                $mvalue2 = $this->randValue();
                foreach ($tables[$table] as $id2 => $data)
                    $tables[$table][$id2][$mkey2] = $mvalue2;
                $db->update($table, array($mkey2 => $mvalue2), array($mkey => $mvalue));
                $this->assertEquals($tables, $db->tables);

                // empty update by non-matching keys
                $db->update($table, array($mkey2 => 0), array($mkey => $mvalue . '.bad', 'id' => $id));
                $this->assertEquals($tables, $db->tables);

                // single update by two keys
                $tables[$table][$id][$mkey2] = 0;
                $db->update($table, array($mkey2 => 0), array($mkey => $mvalue, 'id' => $id));
                $this->assertEquals($tables, $db->tables);
            }
        }
    }

    public function testDelete()
    {
        $db = new \DbVirtual();
        $tables = array();

        for ($i = rand(5, 10); $i >= 0; $i--)
        {
            // generate table
            $table = 'tbl' . rand($i * 100, $i * 100 + 99);
            $tables[$table] = array();

            // generate keys
            $keys = $this->generateKeys(rand(3, 7));
            $mkey = 'k' . rand(100, 499);
            $mkey2 = 'k' . rand(500, 999);
            $mvalue = rand(1000, 4999);

            // insert N values
            for ($j = rand(4, 15); $j >= 0; $j--)
            {
                // insert N records
                $data = $this->generateData($keys, array($mkey => $mvalue, $mkey2 => $this->randValue()));
                $db->insert($table, $data);
                $id = $data['id'] = $db->lastInsertId();
                $tables[$table][$id] = $data;
                $this->assertEquals($db->tables, $tables);
            }

            // "fake" delete by ID
            $db->delete($table, array('id' => $id + 1));
            $this->assertEquals($db->tables, $tables);

            // delete one record by ID
            unset($tables[$table][$id]);
            $db->delete($table, array('id' => $id));
            $this->assertEquals($db->tables, $tables);

            // delete record by id/key
            $data = array_pop($tables[$table]);
            $db->delete($table, array('id' => $data['id'], $mkey2 => $data[$mkey2]));
            $this->assertEquals($db->tables, $tables);


            // delete few records by key
            $tables[$table] = array();
            $db->delete($table, array($mkey => $mvalue));
            $this->assertEquals($db->tables, $tables);

            // insert a few records
            for ($j = rand(3, 8); $j >= 0; $j--)
            {
                // insert N records
                $data = $this->generateData($keys, array($mkey => $mvalue));
                $db->insert($table, $data);
                $id = $data['id'] = $db->lastInsertId();
                $tables[$table][$id] = $data;
                $this->assertEquals($db->tables, $tables);
            }
        }
    }



    public function testTransactions()
    {
        $db = new \DbVirtual();
        $this->assertEquals(array(), $db->tables);

        $db->beginTransaction();
        for ($i = 0; $i < 10; $i++)
        {
            $table = 'tbl' . rand($i * 100, $i * 100 + 99);
            for ($j = 0; $j < 10; $j++)
            {
                $keys = $this->generateKeys(rand(3, 8));
                $db->insert($table, $this->generateData($keys));
            }
        }
        $this->assertNotEquals(array(), $db->tables);

        // rollback and check
        $db->rollBack();
        $this->assertEquals(array(), $db->tables);

        // another transaction
        $db->beginTransaction();
        for ($i = 0; $i < 10; $i++)
        {
            $table = 'tbl' . rand($i * 100, $i * 100 + 99);
            for ($j = 0; $j < 10; $j++)
            {
                $keys = $this->generateKeys(rand(3, 8));
                $db->insert($table, $this->generateData($keys));
            }
        }
        $db->commit();
        $this->assertNotEquals(array(), $db->tables);

        // try another commit -- not in transaction
        try
        {
            $db->commit();
            $this->fail();
        }
        catch (\Exception $e)
        {
            $this->assertEquals(\DbVirtual::ERROR_NO_TRANSACTION, $e->getMessage());
        }

        // try rollback
        try
        {
            $db->rollBack();
            $this->fail();
        }
        catch (\Exception $e)
        {
            $this->assertEquals(\DbVirtual::ERROR_NO_TRANSACTION, $e->getMessage());
        }

        // try to start two transactions in a row
        $db->beginTransaction();
        try
        {
            $db->beginTransaction();
            $this->fail();
        }
        catch (\Exception $e)
        {
            $this->assertEquals(\DbVirtual::ERROR_TRANSACTION, $e->getMessage());
        }
    }

    public function testQuerySimpleWhere()
    {
        $db = new \DbVirtual();

        // create table
        $table = 'tbl' . rand(100, 199);
        $key = 'k' . rand(200, 299);
        $keys = $this->generateKeys(rand(4, 8));

        // insert a record
        $db->insert($table, $data1 = $this->generateData($keys, array($key => rand(100, 299))));
        $data1['id'] = $db->lastInsertId();
        $db->insert($table, $data2 = $this->generateData($keys, array($key => rand(300, 499))));
        $data2['id'] = $db->lastInsertId();
        $db->insert($table, $data3 = $this->generateData($keys, array($key => rand(500, 699))));
        $data3['id'] = $db->lastInsertId();

        // try empty select
        $select = new \DbSelect($table);
        $this->assertEquals(array($data1, $data2, $data3), $db->query($select));

        // select by ID
        $select->where('id', $data1['id']);
        $this->assertEquals(array($data1), $db->query($select));

        // select by other ID
        $select->where('id', $data1['id'] + 1);
        $this->assertEquals(array(), $db->query($select));

        // OR same ID
        $select->whereOr('id', $data1['id']);
        $this->assertEquals(array($data1), $db->query($select));

        // NE
        $select = new \DbSelect($table);
        $select->where('id', \DbSelect::ne($data1['id']));
        $this->assertEquals(array($data2, $data3), $db->query($select));

        // add OR
        $select->whereOr('id', \DbSelect::eq($data1['id']));
        $this->assertEquals(array($data1, $data2, $data3), $db->query($select));

        // LT
        $select->where('id', \DbSelect::lt($data3['id']));
        $this->assertEquals(array($data1, $data2), $db->query($select));

        // LTE
        $select->whereOr('id', \DbSelect::lte($data3['id']));
        $this->assertEquals(array($data1, $data2, $data3), $db->query($select));

        // GT
        $select->where('id', \DbSelect::gt($data1['id']));
        $this->assertEquals(array($data2, $data3), $db->query($select));

        // GTE
        $select->whereOr('id', \DbSelect::gte($data1['id']));
        $this->assertEquals(array($data1, $data2, $data3), $db->query($select));

        // IN
        $select->where('id', \DbSelect::in($data1['id'], $data3['id']));
        $this->assertEquals(array($data1, $data3), $db->query($select));

        // NOT IN
        $select = new \DbSelect($table);
        $select->where('id', \DbSelect::not_in($data1['id'], $data3['id']));
        $this->assertEquals(array($data2), $db->query($select));

        // BETWEEN
        $select->whereOr('id', \DbSelect::between($data1['id'], $data2['id']));
        $this->assertEquals(array($data1, $data2), $db->query($select));

        // two key search
        $select = new \DbSelect($table);
        $select->where('id', \DbSelect::gt($data1['id']))
               ->where($key, \DbSelect::lt($data3[$key]));
        $this->assertEquals(array($data2), $db->query($select));
    }

    public function testQueryWhereNULL()
    {
        $db = new \DbVirtual();

        // create table
        $table = 'tbl' . rand(100, 199);
        $key = 'k' . rand(200, 299);
        $keys = $this->generateKeys(rand(4, 8));

        // insert data
        $db->insert($table, $data1 = $this->generateData($keys, array($key => rand(100, 299))));
        $data1['id'] = $db->lastInsertId();
        $db->insert($table, $data2 = $this->generateData($keys, array($key => rand(300, 499))));
        $data2['id'] = $db->lastInsertId();
        $db->insert($table, $data3 = $this->generateData($keys, array($key => NULL)));
        $data3['id'] = $db->lastInsertId();

        // regular
        $select = new \DbSelect($table);
        $select->where($key, $data1[$key]);
        $this->assertEquals(array($data1), $db->query($select));

        // OR IS NULL
        $select->whereOr($key, \DbSelect::is_null());
        $this->assertEquals(array($data1, $data3), $db->query($select));

        // NULL or value
        $select = new \DbSelect($table);
        $select->where($key, \DbSelect::in($data1[$key], null));
        $this->assertEquals(array($data1, $data3), $db->query($select));

        // IS NOT NULL
        $select = new \DbSelect($table);
        $select->where($key, \DbSelect::not_null());
        $this->assertEquals(array($data1, $data2), $db->query($select));

        // NOT NULL or value
        $select = new \DbSelect($table);
        $select->where($key, \DbSelect::not_in($data2[$key], null));
        $this->assertEquals(array($data1), $db->query($select));
    }

    public function testColumns()
    {
        $db = new \DbVirtual();

        // generate teble/keys/select keys
        $table = 'tbl' . rand(100, 199);
        $keys = $this->generateKeys(rand(6, 10));
        do
        {
            $select_keys = [];
            foreach ($keys as $key)
            {
                if (rand(0, 3) < 2)
                    $select_keys[] = $key;
            }
        } while(!count($select_keys) || (count($keys) == count($select_keys)));

        // generate data
        $expected = [];
        for ($i = rand(5, 8); $i > 0; $i--)
        {
            $db->insert($table, $data = $this->generateData($keys));
            $temp = [];
            foreach ($select_keys as $key)
                $temp[$key] = $data[$key];
            $expected[] = $temp;
        }

        $select = new \DbSelect($table, $select_keys);
        $this->assertEquals($expected, $db->query($select));
    }

    public function testOrder()
    {
        $db = new \DbVirtual();

        // generate teble/keys/select keys
        $table = 'tbl' . rand(100, 199);
        $keys = $this->generateKeys(rand(5, 8));

        // generate data
        $expected = [];
        for ($i = rand(5, 8); $i > 0; $i--)
        {
            $data = $this->generateData($keys);
            $data[$keys[0]] = $i;
            $data[$keys[1]] = 100 - $i;
            $data[$keys[2]] = 'Hello!';
            $db->insert($table, $data);
            $data['id'] = $db->lastInsertId();
            $expected[$i] = $data;
        }

        // sort by k0 asc
        ksort($expected);
        $select = new \DbSelect($table);
        $select->setOrder($keys[0]);
        $this->assertEquals(array_values($expected), $db->query($select));

        $select->setOrder($keys[0] . ' asc');
        $this->assertEquals(array_values($expected), $db->query($select));

        $select->setOrder($keys[0] . ' ASC');
        $this->assertEquals(array_values($expected), $db->query($select));

        $select->setOrder('`' . $keys[0] . '` asc');
        $this->assertEquals(array_values($expected), $db->query($select));

        // sort by k0 desc
        krsort($expected);
        $select->setOrder($keys[0] . ' DESC');
        $this->assertEquals(array_values($expected), $db->query($select));

        // sort by k1 ASC
        $select->setOrder($keys[1]);
        $this->assertEquals(array_values($expected), $db->query($select));

        // k2 ASC, k0 DESC
        $select->setOrder($keys[2] . ', ' . $keys[0] . ' DESC');
        $this->assertEquals(array_values($expected), $db->query($select));
    }

    public function testLimit()
    {
        $db = new \DbVirtual();

        // generate teble/keys/select keys
        $table = 'tbl' . rand(100, 199);
        $keys = $this->generateKeys(rand(5, 8));

        $expected = [];
        for ($i = rand(5, 8); $i > 0; $i--)
        {
            $data = $this->generateData($keys);
            $data[$keys[0]] = 20 - $i;
            $db->insert($table, $data);
            $data['id'] = $db->lastInsertId();
            $expected[$i] = $data;
        }

        // no order test
        $select = new \DbSelect($table);
        $select->setSearchLimit(2);
        $this->assertEquals(array_slice(array_values($expected), 0, 2), $db->query($select));

        $select->setSearchLimit(3, 2);
        $this->assertEquals(array_slice(array_values($expected), 2, 3), $db->query($select));

        // add ASC order clause
        $select->setOrder($keys[0]);
        $this->assertEquals(array_slice(array_values($expected), 2, 3), $db->query($select));

        // add DESC order
        ksort($expected);
        $select->setOrder($keys[0] . ' DESC');
        $this->assertEquals(array_slice(array_values($expected), 2, 3), $db->query($select));
    }

    public function testSimpleJoin()
    {
        $db = new \DbVirtual();

        // generate teble/keys/select keys
        $table = 'tbl' . rand(100, 199);
        $jtable = 'tbl' . rand(200, 299);
        $keys = $this->generateKeys(rand(5, 8));
        $jkeys = $this->generateKeys(rand(2, 5), 'j1_');

        $expected = [];

        $db->insert($table, $data1 = $this->generateData($keys));
        $data1['id'] = $db->lastInsertId();
        $db->insert($jtable, $jdata11 = $this->generateData($jkeys, [$jkeys[0] => $data1[$keys[0]]]));
        $expected[] = array_merge($data1, $jdata11);
        $db->insert($jtable, $jdata12 = $this->generateData($jkeys, [$jkeys[0] => $data1[$keys[0]]]));
        $expected[] = array_merge($data1, $jdata12);

        $db->insert($table, $data2 = $this->generateData($keys));
        $data2['id'] = $db->lastInsertId();
        $db->insert($jtable, $jdata21 = $this->generateData($jkeys, [$jkeys[0] => $data2[$keys[0]]]));
        $expected[] = array_merge($data2, $jdata21);

        $db->insert($table, $data3 = $this->generateData($keys));
        $data3['id'] = $db->lastInsertId();

        $select = new \DbSelect($table);
        $select->join($jtable, [$jkeys[0] => [$table, $keys[0]]], $jkeys);
        $this->assertEquals($expected, $db->query($select));
        
        // try left join
        $jdata3 = [];
        foreach ($jkeys as $key)
            $jdata3[$key] = null;
        $expected[] = array_merge($data3, $jdata3);

        $select = new \DbSelect($table);
        $select->joinLeft($jtable, [$jkeys[0] => [$table, $keys[0]]], $jkeys);
        $this->assertEquals($expected, $db->query($select));
    }

}