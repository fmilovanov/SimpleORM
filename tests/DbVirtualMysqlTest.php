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


}