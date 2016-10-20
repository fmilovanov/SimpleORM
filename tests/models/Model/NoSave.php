<?php
/*
 * @author Felix A. Milovanov
 */

class Model_NoSave extends Model
{
    protected static $_defaults = array(
        'id'            => null,
        'updated_on'    => null,
        'updated_by'    => null
    );
}