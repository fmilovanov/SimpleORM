<?php
/**
  @author Felix A. Milovanov
*/

abstract class Model
{
    private $__is_search_pattern    = false;

    protected $_data                = [];
    protected $_defaults            = [];

    public function setSearchPattern()
    {
        $this->__is_search_pattern = true;
    }

    public function isModel()
    {
        return !$this->__is_search_pattern;
    }

    public function isSearchPattern()
    {
        return $this->__is_search_pattern;
    }

    public function __construct($data = null)
    {
        if (is_array($data))
        {
            if (false)
            {
                $this->_data = $data;
            }
            else
            {
                $this->_data = $this->_defaults;
                foreach ($this->getMapper()->getColumns() as $key => $method)
                {
                    if (array_key_exists($key, $data))
                    {
                        $method = 'set' . $method;
                        $this->$method($data[$key]);
                    }
                }
            }
        }
        elseif ($data == true)
        {
            $this->__is_search_pattern = true;
            $this->_data = $this->_defaults;
            foreach ($this->_data as $key => $_)
                $this->_data[$key] = null;
        }
        elseif (is_null($data))
        {
            $this->_data = $this->_defaults;
        }
        else
        {
            throw new \InvalidArgumentException();
        }
    }

    /**
     * @return \Mapper
     */
    public function getMapper()
    {

    }

    // some getters/setters
    public function getId() { return $this->_data['id']; }
    public function setId($val)
    {
        $this->_data['id'] = $val;
        return $this;
    }

    public function getCreatedOn() { return $this->_data['created_on']; }
    public function setCreatedOn($val)
    {
        $this->_data['created_on'] = $val;
        return $this;
    }

    public function getCreatedBy() { return $this->_data['created_by']; }
    public function setCreatedBy($val)
    {
        $this->_data['created_by'] = $val;
        return $this;
    }

    public function getUpdatedOn() { return $this->_data['updated_on']; }
    public function setUpdatedOn($val)
    {
        $this->_data['updated_on'] = $val;
        return $this;
    }

    public function getUpdatedBy() { return $this->_data['updated_by']; }
    public function setUpdatedBy($val)
    {
        $this->_data['updated_by'] = $val;
        return $this;
    }
}
