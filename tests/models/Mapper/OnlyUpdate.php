<?php
/*
 * @author Felix A. Milovanov
 */

class Mapper_OnlyUpdate extends Mapper
{
    protected $_save_allowed = self::SAVE_UPDATE;

    public function getColumns()
    {
        return array(
            'updated_on'    => 'UpdatedOn',
            'updated_by'    => 'UpdatedBy'
        );
    }
}
