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
}