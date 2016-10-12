<?php
/*
 * @author Felix A. Milovanov
 */

class Mapper_ComplexModel extends Mapper
{
    public function getColumns()
    {
        return array(
            'x1'            => 'X1',
            'x2'            => 'X2',
            'created_on'    => 'CreatedOn',
            'updated_on'    => 'UpdatedOn',
            'deleted_on'    => 'DeletedOn'
        );
    }
}
