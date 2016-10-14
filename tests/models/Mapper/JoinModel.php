<?php
/*
 * @author Felix A. Milovanov
 */

class Mapper_JoinModel extends Mapper
{
    public function getColumns()
    {
        return array(
            'status_id' => 'StatusId'
        );
    }

    protected function addJoins(\DbSelect $select)
    {
        $select->join('statuses', array('id' => array($this->getTableName(), 'status_id')), array('name' => 'status'));
    }
}
