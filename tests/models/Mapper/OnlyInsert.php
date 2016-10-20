<?php
/*
 * @author Felix A. Milovanov
 */

class Mapper_OnlyInsert extends Mapper
{
    protected $_save_allowed = self::SAVE_INSERT;

    public function getColumns()
    {
        return array(
            'updated_on'    => 'UpdatedOn',
            'updated_by'    => 'UpdatedBy'
        );
    }
}
