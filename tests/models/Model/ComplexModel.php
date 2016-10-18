<?php
/*
 * @author Felix A. Milovanov
 */

class Model_ComplexModel extends Model
{
    public $validated   = false;

    protected static $_friends = array('Test_Model');
    protected static $_defaults = array(
        'id'            => null,
        'x1'            => null,
        'x2'            => null,
        'created_on'    => null,
        'updated_on'    => null,
        'deleted_on'    => null
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
        $this->_data['x2'] = $val;
        return $this;
    }

    public function getDeletedOn() { return $this->_data['deleted_on']; }
    public function setDeletedOn($val)
    {
        $this->_data['deleted_on'] = $val;
        return $this;
    }

    // other functions
    public function validate()
    {
        $this->validated = true;
    }
}

