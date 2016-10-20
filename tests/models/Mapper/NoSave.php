<?php
/*
 * @author Felix A. Milovanov
 */

class Mapper_NoSave extends Mapper
{
    protected $_save_allowed = 0;

    public function getColumns()
    {
        return array(
            'updated_on'    => 'UpdatedOn',
            'updated_by'    => 'UpdatedBy'
        );
    }
}
