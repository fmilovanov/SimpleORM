<?php
/*
 * @author Felix A. Milovanov
 */

class Model_JoinModel extends Model
{
    protected static $_defaults = array(
        'id'        => null,
        'status_id' => null,
        'status'    => ''
    );

    public function getStatusId() { return $this->_data['status_id']; }
    public function setStatusId($val)
    {
        $this->_data['status_id'] = trim($val);
        return $this;
    }

    public function getStatus() { return $this->_data['status']; }
    public function setStatus($val)
    {
        $this->_data['status'] = trim($val);
        return $this;
    }
}
