<?php
require_once(__DIR__ . '/SimpleORM/IDbAdapter.php');
require_once(__DIR__ . '/SimpleORM/DbException.php');
require_once(__DIR__ . '/SimpleORM/DbSelect.php');
require_once(__DIR__ . '/SimpleORM/DbSql.php');
require_once(__DIR__ . '/SimpleORM/Model.php');
require_once(__DIR__ . '/SimpleORM/Mapper.php');

require_once(__DIR__ . '/samples/Model/Company.php');
require_once(__DIR__ . '/samples/Mapper/Company.php');

date_default_timezone_set('America/New_York');

$pdo = new PDO('mysql:host=localhost;dbname=felix', 'root', '');
$adapter = new \DbSql($pdo);
\Mapper::setDefaultDbAdapter($adapter);

//$company = new Model_Company();
//$company->setName('Felix Company');
//$company->save();

print_r(Mapper_Company::getInstance()->find(2));

