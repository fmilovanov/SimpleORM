<?php
/*
 * @author Felix A. Milovanov
 */
require_once(__DIR__ . '/Abstract.php');

class Test_Model extends Test_Abstract
{
    public function testGetTableName()
    {
        $iv = mcrypt_create_iv(8);
    }
}