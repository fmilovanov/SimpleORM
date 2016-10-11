<?php
/*
 * @author Felix A. Milovanov
 */

class Mapper_Company extends Mapper
{
    public function getColumns()
    {
        return array(
            'name'      =>  'Name',
            'created_on'    => 'CreatedOn',
            'updated_on'    => 'UpdatedOn',
        );
    }
}
