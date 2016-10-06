<?php
/**
  @author Felix A. Milovanov
*/

abstract class Model
{
    private $__is_search_pattern    = false;

    protected $_data                = array();

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
        if (!isset(static::$__defaults))
            throw new \Exception('No defaults defined');

        $this->_data = static::$__defaults;

        if (is_array($data))
        {
            if (false)
            {
                $this->_data = $data;
            }
            else
            {
                if (array_key_exists('id', $data))
                    $this->setId($data['id']);

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
        elseif ($data === false)
        {
            $this->__is_search_pattern = true;
            foreach ($this->_data as $key => $_)
                $this->_data[$key] = null;
        }
        elseif (!is_null($data))
        {
            throw new \InvalidArgumentException();
        }
    }

    /**
     * @return Mapper
     * @throws \Exception
     */
    public function getMapper()
    {
        $class = get_class($this);
        if (preg_match('/^Model_/', $class))
            $class = preg_replace('/^Model_/', 'Mapper_', $class);
        else
            throw new \Exception('Cannot determine mapper name');

        return $class::getInstance();
    }

    public function toArray()
    {
        return $this->_data;
    }

    public final function save()
    {
        return $this->getMapper()->save($this);
    }

    public final function search()
    {
        return $this->getMapper()->search($this);
    }

    public function delete($head = false)
    {
        $this->getMapper()->delete($this);
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
