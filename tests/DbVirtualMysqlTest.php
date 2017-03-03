<?php
/*
 * @author Felix A. Milovanov
 */
require_once(__DIR__ . '/Abstract.php');
require_once(__DIR__ . '/mocks/PDOMySQL.php');
require_once(dirname(__DIR__) . '/SimpleORM/DbVirtual.php');
require_once(dirname(__DIR__) . '/SimpleORM/DbSelect.php');
require_once(dirname(__DIR__) . '/SimpleORM/DbException.php');

class Test_DbVirtualMySQL extends Test_Abstract
{
    private static $__int_base;

    protected function int()
    {
        if (is_null(self::$__int_base))
            self::$__int_base = rand(10, 99);

        self::$__int_base += rand(2, 10);
        return self::$__int_base;
    }

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

    public function testNoForeignKeys()
    {
        $table = 'tbl' . rand(100, 999);
        $create_table = "CREATE TABLE `$table` (\n";
        $schema = [];

        // int field
        $field = 'key' . $this->int();
        $default = rand(1000, 9999);
        $create_table .= "  `$field` int(11) NOT NULL DEFAULT '$default',\n";
        $schema[$field] = (object) ['null' => false, 'type' => DbVirtual::TYPE_INT, 'len' => 11, 'default' => $default];

        // various ints
        foreach (['tinyint', 'smallint', 'mediumint', 'bigint'] as $type)
        {
            $field = 'key' . $this->int();
            $default = rand(1000, 9999);
            $len = rand(2, 11);
            $create_table .= "  `$field` $type($len) NOT NULL DEFAULT '$default',\n";
            $schema[$field] = (object) ['null' => false, 'type' => DbVirtual::TYPE_INT, 'len' => $len, 'default' => $default];

        }

        // float field
        $field = 'key' . $this->int();
        $default = rand(1000, 9999) / 100;
        $create_table .= "  `$field` float NOT NULL DEFAULT '$default',\n";
        $schema[$field] = (object) ['null' => false, 'type' => DbVirtual::TYPE_FLOAT, 'default' => $default];

        // double
        $field = 'key' . $this->int();
        $default = rand(1000, 9999) / 100;
        $create_table .= "  `$field` double NOT NULL DEFAULT '$default',\n";
        $schema[$field] = (object) ['null' => false, 'type' => DbVirtual::TYPE_FLOAT, 'default' => $default];

        // decimal, null
        $field = 'key' . $this->int();
        $create_table .= "  `$field` decimal(5, 3) DEFAULT NULL,\n";
        $schema[$field] = (object) ['null' => true, 'type' => DbVirtual::TYPE_FLOAT];

        // regular char
        $field = 'key' . $this->int();
        $len = rand(32, 48);
        $default = $this->randValue();
        $create_table .= "  `$field` char($len) NOT NULL DEFAULT '$default',\n";
        $schema[$field] = (object) ['null' => false, 'type' => DbVirtual::TYPE_CHAR, 'len' => $len, 'default' => $default];

        // varchar, default null
        $field = 'key' . $this->int();
        $len = rand(32, 48);
        $create_table .= "  `$field` varchar($len) DEFAULT '',\n";
        $schema[$field] = (object) ['null' => true, 'type' => DbVirtual::TYPE_CHAR, 'len' => $len, 'default' => ''];

        // varuous blobs
        foreach (['text', 'tinytext', 'mediumtext', 'longtext', 'blob', 'tinyblob', 'mediumblob', 'longblob'] as $type)
        {
            $field = 'key' . $this->int();
            $create_table .= "  `$field` $type DEFAULT NULL,\n";
            $schema[$field] = (object) ['null' => true, 'type' => DbVirtual::TYPE_CHAR, 'len' => 65536];
        }

        // enum
        $enum = [];
        for ($i = rand(3, 8); $i > 0; $i--)
            $enum[] = 'val' . rand(100, 999);
        $field = 'key' . $this->int();
        $default = $enum[0];
        $create_table .= "  `$field` enum('" . implode("','", $enum) . "') NOT NULL DEFAULT '$default',\n";
        $schema[$field] = (object) ['null' => false, 'type' => DbVirtual::TYPE_ENUM, 'values' => $enum,
                                    'default' => $default];

        // date
        $field = 'key' . $this->int();
        $default = date('Y-m-d', time() - rand(100, 999) * 24 * 60 * 60);
        $create_table .= "  `$field` date NOT NULL DEFAULT '$default',\n";
        $schema[$field] = (object) ['null' => false, 'type' => DbVirtual::TYPE_DATE, 'default' => $default];

        // datetime, default current timestamp
        $field = 'key' . $this->int();
        $create_table .= "  `$field` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,\n";
        $schema[$field] = (object) ['null' => false, 'type' => DbVirtual::TYPE_DATETIME,
                                    'default' => DbVirtual::DEFAULT_CTS];

        // datetime, on update current timestamp
        $field = 'key' . $this->int();
        $create_table .= "  `$field` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n";
        $schema[$field] = (object) ['null' => false, 'type' => DbVirtual::TYPE_DATETIME,
                                    'default' => DbVirtual::DEFAULT_CTS, 'on_update' => DbVirtual::DEFAULT_CTS];

        // finalyze
        $create_table .= ")\n";

        $pdo = new PDOMySQL();
        $pdo->tables[$table] = $create_table;

        $db = new DbVirtual($pdo);
        $db->createTable($table);

        $this->assertEquals($schema, $db->getTableDef($table));
    }

    private function _insertInvalids(DbVirtual $db, $table, array $values, $key, $error, array $expected = [])
    {
        // try non-scalar values
        foreach ([[], [1], new \stdClass()] as $value)
        {
            try
            {
                $db->insert($table, [$key => $value]);
                $this->fail();
            }
            catch (\Exception $e)
            {
                $this->assertEquals(sprintf(DbVirtual::ERROR_VALUE_NOT_SCALAR, $key), $e->getMessage());
                $this->assertEquals($expected, $db->tables[$table]);
            }
        }

        // try invalid values
        foreach ($values as $value)
        {
            try
            {
                $db->insert($table, [$key => $value]);
                $this->fail();
            }
            catch (\Exception $e)
            {
                $this->assertEquals(sprintf($error, $key), $e->getMessage());
                $this->assertEquals($expected, $db->tables[$table]);
            }
        }
    }

    private function _updateInvalids(DbVirtual $db, $table, array $values, $key, $error, array $expected)
    {
        $id = array_pop(array_values($expected));

        // non-scalar values
        foreach ([[], [1], new \stdClass()] as $value)
        {
            try
            {
                $db->update($table, [$key => $value], ['id' => $id]);
                $this->fail();
            }
            catch (\Exception $e)
            {
                $this->assertEquals(sprintf(DbVirtual::ERROR_VALUE_NOT_SCALAR, $key), $e->getMessage());
                $this->assertEquals($expected, $db->tables[$table]);
            }
        }

        // invalid values
        foreach ($values as $value)
        {
            try
            {
                $db->update($table, [$key => $value], ['id' => $id]);
                $this->fail();
            }
            catch (\Exception $e)
            {
                $this->assertEquals(sprintf($error, $key), $e->getMessage());
                $this->assertEquals($expected, $db->tables[$table]);
            }
        }
    }

    private function _validateTypeNotNullDefault($type, $default, array $invalid, array $valid, $error)
    {
        $table = 'tbl' . rand(100, 999);
        $key = 'key' . rand(100, 999);

        $pdo = new PDOMySQL();
        $pdo->tables[$table] = "CREATE TABLE `$table`(\n  `$key` $type NOT NULL DEFAULT '$default'\n)";

        $db = new DbVirtual($pdo);
        $db->createTable($table);

        $expected = [];

        // try invalid values
        $this->_insertInvalids($db, $table, $invalid, $key, $error, $expected);

        // try valid values
        foreach ($valid as $value)
        {
            $db->insert($table, [$key => $value]);
            $id = $db->lastInsertId();
            $expected[$id] = [$key => $value, 'id' => $id];
            $this->assertEquals($expected, $db->tables[$table]);
        }

        // try NULL
        $db->insert($table, [$key => NULL]);
        $id = $db->lastInsertId();
        $expected[$id] = [$key => $default, 'id' => $id];
        $this->assertEquals($expected, $db->tables[$table]);

        // try missing
        $db->insert($table, []);
        $id = $db->lastInsertId();
        $expected[$id] = [$key => $default, 'id' => $id];
        $this->assertEquals($expected, $db->tables[$table]);


        // try update invalid
        $this->_updateInvalids($db, $table, $invalid, $key, $error, $expected);

        // try update valids
        foreach ($valid as $value)
        {
            $db->update($table, [$key => $value], ['id' => $id]);
            $expected[$id][$key] = $value;
            $this->assertEquals($expected, $db->tables[$table]);
        }

        // try update NULL
        try
        {
            $db->update($table, [$key => NULL], ['id' => $id]);
            $this->fail();
        }
        catch (\Exception $e)
        {
            $this->assertEquals(sprintf(DbVirtual::ERROR_VALUE_NULL, $key), $e->getMessage());
            $this->assertEquals($expected, $db->tables[$table]);
        }
    }

    private function _validateTypeNullDefault($type, $default, array $invalid, array $valid, $error)
    {
        $table = 'tbl' . rand(100, 999);
        $key = 'key' . rand(100, 999);

        $pdo = new PDOMySQL();
        $pdo->tables[$table] = "CREATE TABLE `$table`(\n  `$key` $type DEFAULT '$default'\n)";

        $db = new DbVirtual($pdo);
        $db->createTable($table);

        $expected = [];

        // try invalid values
        $this->_insertInvalids($db, $table, $invalid, $key, $error, $expected);

        // try valid values
        foreach ($valid as $value)
        {
            $db->insert($table, [$key => $value]);
            $id = $db->lastInsertId();
            $expected[$id] = [$key => $value, 'id' => $id];
            $this->assertEquals($expected, $db->tables[$table]);
        }

        // try NULL
        $db->insert($table, [$key => NULL]);
        $id = $db->lastInsertId();
        $expected[$id] = [$key => NULL, 'id' => $id];
        $this->assertEquals($expected, $db->tables[$table]);

        // try missing
        $db->insert($table, []);
        $id = $db->lastInsertId();
        $expected[$id] = [$key => $default, 'id' => $id];
        $this->assertEquals($expected, $db->tables[$table]);

        // try update invalid
        $this->_updateInvalids($db, $table, $invalid, $key, $error, $expected);

        // try update valids
        foreach ($valid as $value)
        {
            $db->update($table, [$key => $value], ['id' => $id]);
            $expected[$id][$key] = $value;
            $this->assertEquals($expected, $db->tables[$table]);
        }

        // try update NULL
        $db->update($table, [$key => NULL], ['id' => $id]);
        $expected[$id] = [$key => NULL, 'id' => $id];
        $this->assertEquals($expected, $db->tables[$table]);
    }


    private function _validateTypeNotNullNoDefault($type, $default, array $invalid, array $valid, $error)
    {
        $table = 'tbl' . rand(100, 999);
        $key = 'key' . rand(100, 999);

        $pdo = new PDOMySQL();
        $pdo->tables[$table] = "CREATE TABLE `$table`(\n  `$key` $type NOT NULL\n)";

        $db = new DbVirtual($pdo);
        $db->createTable($table);

        $expected = [];

        // try invalid values
        $this->_insertInvalids($db, $table, $invalid, $key, $error, $expected);

        // try valid values
        foreach ($valid as $value)
        {
            $db->insert($table, [$key => $value]);
            $id = $db->lastInsertId();
            $expected[$id] = [$key => $value, 'id' => $id];
            $this->assertEquals($expected, $db->tables[$table]);
        }

        // try NULL
        try
        {
            $db->insert($table, [$key => NULL]);
            $this->fail();
        }
        catch (\Exception $e)
        {
            $this->assertEquals(sprintf(DbVirtual::ERROR_VALUE_NO_DEFAULT, $key), $e->getMessage());
            $this->assertEquals($expected, $db->tables[$table]);
        }

        // no value
        try
        {
            $db->insert($table, []);
            $this->fail();
        }
        catch (\Exception $e)
        {
            $this->assertEquals(sprintf(DbVirtual::ERROR_VALUE_NO_DEFAULT, $key), $e->getMessage());
            $this->assertEquals($expected, $db->tables[$table]);
        }

        // update invalid values
        $this->_updateInvalids($db, $table, $invalid, $key, $error, $expected);

        // update valid values
        foreach ($valid as $value)
        {
            $db->update($table, [$key => $value], ['id' => $id]);
            $expected[$id][$key] = $value;
            $this->assertEquals($expected, $db->tables[$table]);
        }

        // update NULL
        try
        {
            $db->update($table, [$key => NULL], ['id' => $id]);
            $this->fail();
        }
        catch (\Exception $e)
        {
            $this->assertEquals(sprintf(DbVirtual::ERROR_VALUE_NULL, $key), $e->getMessage());
            $this->assertEquals($expected, $db->tables[$table]);
        }
    }

    private function _validateTypeNullNoDefault($type, $default, array $invalid, array $valid, $error)
    {
        $table = 'tbl' . rand(100, 999);
        $key = 'key' . rand(100, 999);

        $pdo = new PDOMySQL();
        $pdo->tables[$table] = "CREATE TABLE `$table`(\n  `$key` $type DEFAULT NULL\n)";

        $db = new DbVirtual($pdo);
        $db->createTable($table);

        $expected = [];

        // try invalid values
        $this->_insertInvalids($db, $table, $invalid, $key, $error, $expected);

        // try valid values
        foreach ($valid as $value)
        {
            $db->insert($table, [$key => $value]);
            $id = $db->lastInsertId();
            $expected[$id] = [$key => $value, 'id' => $id];
            $this->assertEquals($expected, $db->tables[$table]);
        }

        // try NULL
        $db->insert($table, [$key => NULL]);
        $id = $db->lastInsertId();
        $expected[$id] = [$key => NULL, 'id' => $id];
        $this->assertEquals($expected, $db->tables[$table]);

        // try no value
        $db->insert($table, []);
        $id = $db->lastInsertId();
        $expected[$id] = [$key => NULL, 'id' => $id];
        $this->assertEquals($expected, $db->tables[$table]);

        // update invalid values
        $this->_updateInvalids($db, $table, $invalid, $key, $error, $expected);

        // update valid values
        foreach ($valid as $value)
        {
            $db->update($table, [$key => $value], ['id' => $id]);
            $expected[$id][$key] = $value;
            $this->assertEquals($expected, $db->tables[$table]);
        }

        $db->update($table, [$key => NULL], ['id' => $id]);
        $expected[$id][$key] = NULL;
        $this->assertEquals($expected, $db->tables[$table]);
    }


    protected function validateType($type, $default, array $invalid, array $valid, $error)
    {
        $this->_validateTypeNotNullDefault($type, $default, $invalid, $valid, $error);
        $this->_validateTypeNullDefault($type, $default, $invalid, $valid, $error);
        $this->_validateTypeNotNullNoDefault($type, $default, $invalid, $valid, $error);
        $this->_validateTypeNullNoDefault($type, $default, $invalid, $valid, $error);
    }


    public function testInt()
    {
        $default = rand(100, 999);
        $invalid = ['hello', false, 1.15];
        $valid = [0, -1, rand(1000, 4999), rand(5999, 9999) . ''];

        $this->validateType('int(11)', $default, $invalid, $valid, DbVirtual::ERROR_VALUE_INT);
    }

    public function testChar()
    {
        $len = rand(32, 50);
        $default = $this->randValue(16);
        $invalid = [];
        for ($i = rand(5, 6); $i > 1; $i--)
            $invalid[] = $this->randValue($len + $i );

        $valid = [$default, 0, 1, $this->randValue($len)];

        $this->validateType("char($len)", $default, $invalid, $valid, DbVirtual::ERROR_VALUE_CHAR);
    }


    public function testFloat()
    {
        $default = rand(100, 999) / 100;
        $invalid = ['hello', false];
        $valid = [0, -1, rand(1000, 4999) / 100, (rand(5999, 9999) / 100) . '', rand(1000, 1199), '.' . rand(100, 199),
                  rand(100, 199) . '.'];

        $this->validateType('float', $default, $invalid, $valid, DbVirtual::ERROR_VALUE_FLOAT);
    }

    public function testEnum()
    {
        $enum = [];
        for ($i = rand(4, 8); $i >= 0; $i--)
            $enum[] = 'val' . rand(100, 999);

        $type = "enum('" . implode("','", $enum) . "')";
        $default = $enum[0];
        $invalid = ['hello', false, -1, 33, 1.66];

        $this->validateType($type, $enum[0], $invalid, $enum, DbVirtual::ERROR_VALUE_ENUM);
    }

    public function testDate()
    {
        $default = date('Y-m-d', time() - rand(3, 20) * 24 * 60 * 60);
        $invalid = ['hello', false, -1, 44, 4.66, date('Y-m-d H:i:s')];
        $valid = [];
        for ($i = rand(3, 8); $i >= 0; $i--)
            $valid[] = date('Y-m-d', time() - $i * 24 * 60 * 60);

        $this->validateType('date', $default, $invalid, $valid, DbVirtual::ERROR_VALUE_DATE);
    }

    public function testDatetime()
    {
        $default = date('Y-m-d H:i:s', time() - rand(3, 20) * 24 * 60 * 60);
        $invalid = ['hello', false, -1, 44, 4.66, date('Y-m-d')];
        $valid = [];
        for ($i = rand(3, 8); $i >= 0; $i--)
            $valid[] = date('Y-m-d H:i:s', time() - $i * 8 * 60 * 60);

        $this->validateType('datetime', $default, $invalid, $valid, DbVirtual::ERROR_VALUE_DATETIME);
    }

    public function testCreateForeignKeys()
    {
        $table1 = 'tbl' . rand(100, 499);
        $key1 = 'key' . $this->int();
        $ct1 = "CREATE TABLE `$table1` (\n"
             . "  `$key1` int(11) NOT NULL\n"
             . ");\n";


        $table2 = 'tbl' . rand(500, 599);
        $key2 = 'key' . $this->int();
        $ref = "fk_${table2}_${key2}";
        $ct2 = "CREATE TABLE `$table2` (\n"
             . "  `$key2` int(11) NOT NULL,\n"
             . "  CONSTRAINT `$ref` FOREIGN KEY (`$key2`) REFERENCES `$table1` (`$key1`)\n"
             . ");\n";


        $pdo = new PDOMySQL();
        $pdo->tables[$table1] = $ct1;
        $pdo->tables[$table2] = $ct2;

        $db = new DbVirtual($pdo);

        // try to create 2nd table first
        try
        {
            $db->createTable($table2);
            $this->fail();
        }
        catch (\Exception $e)
        {
            $this->assertEquals(sprintf(DbVirtual::ERROR_NO_REF_TABLE, $ref), $e->getMessage());
            $this->assertEquals([], $db->tables);
            try
            {
                $db->getTableDef($table2);
                $this->fail();
            }
            catch (\Exception $e)
            {
                $this->assertEquals('No table found', $e->getMessage());
            }
        }

        // create 1st table
        $db->createTable($table1);
        $def1 = $db->getTableDef($table1);
        $this->assertArrayHasKey($key1, $def1);
        $this->assertObjectNotHasAttribute('ref', $def1[$key1]);

        // create 2nd table
        $db->createTable($table2);
        $def2 = $db->getTableDef($table2);
        $this->assertArrayHasKey($key2, $def2);
        $this->assertObjectHasAttribute('fk', $def2[$key2]);
        $this->assertEquals([$ref => [$table1, $key1]], $def2[$key2]->fk);

        // check 1st table def
        $def1 = $db->getTableDef($table1);
        $this->assertArrayHasKey($key1, $def1);
        $this->assertObjectHasAttribute('ref', $def1[$key1]);
        $this->assertEquals([$ref => [$table2, $key2]], $def1[$key1]->ref);

        // try to insert w/out a ref
        $value1 = rand(1000, 1999);
        try
        {
            $db->insert($table2, [$key2 => $value1]);
            $this->fail();
        }
        catch (\PDOException $e)
        {
            $error = sprintf(DbVirtual::ERROR_FOREIGN_KEY_CHILD, $table2, $ref, $key2, $table1, $key1);
            $this->assertEquals($error, $e->getMessage());
        }

        // insert ref
        $db->insert($table1, [$key1 => $value1]);
        $id11 = $db->lastInsertId();
        $db->insert($table2, [$key2 => $value1]);
        $id21 = $db->lastInsertId();

        // try to update to an empty ref
        $value2 = rand(2000, 2999);
        try
        {
            $db->update($table2, [$key2 => $value2], ['id' => $id21]);
            $this->fail();
        }
        catch (\PDOException $e)
        {
            $error = sprintf(DbVirtual::ERROR_FOREIGN_KEY_CHILD, $table2, $ref, $key2, $table1, $key1);
            $this->assertEquals($error, $e->getMessage());
        }

        // insert another ref value
        $db->insert($table1, [$key1 => $value2]);
        $id12 = $db->lastInsertId();
        $db->update($table2, [$key2 => $value2], ['id' => $id21]);

        // update wrong record of parent table
        $value3 = rand(3000, 3999);
        try
        {
            $db->update($table1, [$key1 => $value3], ['id' => $id12]);
            $this->fail();
        }
        catch (\PDOException $e)
        {
            $error = sprintf(DbVirtual::ERROR_FOREIGN_KEY_PARENT, $table1, $ref, $key1, $table2, $key2);
            $this->assertEquals($error, $e->getMessage());
        }

        // update child table
        $db->update($table2, [$key2 => $value1], ['id' => $id21]);
        $db->update($table1, [$key1 => $value3], ['id' => $id12]);

        // delete a row in parent table
        $db->delete($table1, ['id' => $id12]);
        try
        {
            $db->delete($table1, ['id' => $id11]);
            $this->fail();
        }
        catch (\PDOException $e)
        {
            $error = sprintf(DbVirtual::ERROR_FOREIGN_KEY_PARENT, $table1, $ref, $key1, $table2, $key2);
            $this->assertEquals($error, $e->getMessage());
        }
        
    }

}