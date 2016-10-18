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
            'created_by'    => 'CreatedBy',
            'updated_on'    => 'UpdatedOn',
            'updated_by'    => 'UpdatedBy',
            'deleted_on'    => 'DeletedOn'
        );
    }
}
