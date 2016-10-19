<?php
/*
 * @author Felix A. Milovanov
 */
require_once(__DIR__ . '/Abstract.php');
require_once(dirname(__DIR__) . '/SimpleORM/DbVirtual.php');
require_once(dirname(__DIR__) . '/SimpleORM/DbSelect.php');
require_once(dirname(__DIR__) . '/SimpleORM/DbException.php');

class Test_DbSql extends Test_Abstract
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
}