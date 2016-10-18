<?php
/**
  @author Felix A. Milovanov
*/

abstract class Model
{
    public static $__friends = array();
    public static $__friends_depth = array();
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
        if (!isset(static::$_defaults))
            throw new \Exception('No defaults defined');

        $this->_data = static::$_defaults;

        if (is_array($data))
        {
            $class = get_class($this);
            if (!array_key_exists($class, self::$__friends))
            {
                self::$__friends[$class] = array('Mapper' => true, get_class($this->getMapper()) => true);
                if (isset(static::$_friends))
                {
                    foreach (static::$_friends as $cname)
                        self::$__friends[$class][$cname] = true;
                }

                self::$__friends_depth[$class] = count(class_parents($this)) + 3;
            }

            // check what options to use -- PHP versions are different
            $version = explode('.', phpversion());
            $version_major = $version[0] . '.' . $version[1];
            if ($version_major < 5.4)
            {
                if (($version_major < 5.3) || ($version[2] < 6))
                {
                    $backtraces = debug_backtrace(false);
                }
                else
                {
                    $backtraces = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
                }
            }
            else
            {
                $backtraces = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, self::$__friends_depth[$class]);
            }

            $is_friend = false;
            foreach ($backtraces as $backtrace)
            {
                // check if we're in constructor
                if (($backtrace['function'] == '__construct') && ($backtrace['type'] == '->'))
                    continue;

                // check if we're in getModel
                if (($backtrace['function'] == 'getModel') && ($backtrace['type'] == '->'))
                    continue;

                // check calling class
                if (isset(self::$__friends[$class][$backtrace['class']]))
                    $is_friend = true;

                // we can exit -- no need to check deeper
                break;
            }


            if ($is_friend)
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

    public final function search($order = null)
    {
        return $this->getMapper()->search($this, $order);
    }

    public function delete($hard = false)
    {
        $this->getMapper()->delete($this, $hard);
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
