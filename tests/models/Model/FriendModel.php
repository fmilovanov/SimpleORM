<?php
/*
 * @author Felix A. Milovanov
 */

class Model_FriendModel extends Model
{
    const X1_DEFAULT    = '1';
    const X2_DEFAULT    = '1';

    protected static $_friends = array(
        'Test_Model'
    );

    protected static $_defaults = array(
        'id'    => null,
        'x1'    => self::X1_DEFAULT,
        'x2'    => self::X2_DEFAULT
    );

    public function getX1() { return $this->_data['x1']; }
    public function setX1($val)
    {
        $this->_data['x1'] = trim($val);
        return $this;
    }

    public function getX2() { return $this->_data['x2']; }
    public function setX2($val)
    {
        $this->_data['x2'] = trim($val);
        return $this;
    }
}
