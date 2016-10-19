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

    public function testUpdate()
    {
        $db = new \DbVirtual();

        for ($i = 0; $i < 10; $i++)
        {
            // generate table
            $table = 'tbl' . rand($i * 100, $i * 100 + 99);

            // generate keys
            $keys = array();
            $mkey = 'k' . rand(100, 499);
            $mkey2 = 'k' . rand(500, 999);
            $mvalue = rand(1000, 9999);
            for ($j = rand(2, 5); $j >= 0; $j--)
                $keys[] = 'k' . rand($j * 10, $j * 10 + 9);

            $tcontent = array();
            for ($j = 0; $j < 10; $j++)
            {
                $data = array($mkey => $mvalue, $mkey2 => $this->randValue());
                foreach ($keys as $key)
                    $data[$key] = $this->randValue();

                // insert and check
                $db->insert($table, $data);
                $id = $data['id'] = $db->lastInsertId();
                $tcontent[$id] = $data;
                $this->assertEquals($tcontent, $db->tables[$table]);

                // update single record by ID
                foreach ($keys as $key)
                    $data[$key] = $this->randValue();
                $tcontent[$id] = $data;
                $db->update($table, $data, array('id' => $id));
                $this->assertEquals($tcontent, $db->tables[$table]);

                // update all records by mkey
                $mvalue2 = $this->randValue();
                foreach ($tcontent as $id2 => $data)
                    $tcontent[$id2][$mkey2] = $mvalue2;
                $db->update($table, array($mkey2 => $mvalue2), array($mkey => $mvalue));
                $this->assertEquals($tcontent, $db->tables[$table]);

                // empty update by non-matching keys
                $db->update($table, array($mkey2 => 0), array($mkey => $mvalue . '.bad', 'id' => $id));
                $this->assertEquals($tcontent, $db->tables[$table]);

                // single update by two keys
                $tcontent[$id][$mkey2] = 0;
                $db->update($table, array($mkey2 => 0), array($mkey => $mvalue, 'id' => $id));
                $this->assertEquals($tcontent, $db->tables[$table]);
            }
        }
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
            $keys = array();
            $mkey = 'k' . rand(100, 499);
            $mkey2 = 'k' . rand(500, 999);
            $mvalue = rand(1000, 4999);
            for ($j = rand(2, 5); $j >= 0; $j--)
                $keys[] = 'k' . rand($j * 10, $j * 10 + 9);

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