<?php
/*
 * @author Felix A. Milovanov
 */
require_once(__DIR__ . '/Abstract.php');
require_once(__DIR__ . '/mocks/PDOMySQL.php');
require_once(dirname(__DIR__) . '/SimpleORM/DbVirtual.php');
require_once(dirname(__DIR__) . '/SimpleORM/DbSelect.php');
require_once(dirname(__DIR__) . '/SimpleORM/DbException.php');

class Test_DbVirtual extends Test_Abstract
{
    public function testTableNotExist()
    {
        $table = 'tbl' . rand(100, 999);

        $pdo = new PDOMySQL();
        $pdo->tables[$table] = "CREATE TABLE `xxx`(\n `id` int(11) NOT NULL\n)";

        $db = new DbVirtual($pdo);

        // try non-existing tables
        for ($i = 0; $i < 10; $i++)
        {
            $tbl = 'tbl' . rand(1000, 9999);
            try
            {
                $db->createTable($tbl);
                $this->fail();
            }
            catch (Exception $e)
            {
                $this->assertEquals(PDOMySQL::ERROR_TABLE, $e->getMessage());
            }
        }

        // try my table
        $db->createTable($table);
    }


}