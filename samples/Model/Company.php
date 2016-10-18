<?php
/*
 * @author Felix A. Milovanov
 */

class Model_Company extends Model
{
    protected static $_defaults = array(
        'id'            => null,
        'name'          => null,
        'created_on'    => null,
        'updated_on'    => null
    );

    public function getName() { return $this->_data['name']; }
    public function setName($val)
    {
        if ($this->isModel())
        {
            $val = trim($val);
        }
        $this->_data['name'] = $val;
        return $this;
    }
}