<?php
/*
 * @author Felix A. Milovanov
 */
require_once(dirname(__DIR__) . '/SimpleORM/Model.php');
require_once(dirname(__DIR__) . '/SimpleORM/Mapper.php');
require_once(dirname(__DIR__) . '/SimpleORM/DbSelect.php');
require_once(dirname(__DIR__) . '/SimpleORM/IDbAdapter.php');

date_default_timezone_set('America/New_York');

abstract class Test_Abstract extends PHPUnit_Framework_TestCase
{
    protected function randValue($len = 32)
    {
        return substr(hash('sha256',  microtime(true) . rand(0, 9999999999)), 0, $len);
    }
}