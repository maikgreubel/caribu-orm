<?php
namespace Nkey\Caribu\Model;

/**
 * Abstract entity model
 *
 * This class is part of Caribu package
 *
 * @author Maik Greubel <greubel@nkey.de>
 */
abstract class AbstractModel extends \Nkey\Caribu\Orm\Orm
{

    public function __construct()
    {
        // Needed
    }

    /**
     * Generate an array of all values of model
     *
     * @return array
     */
    public function toArray()
    {
        $values = array();
        $rf = new \ReflectionClass(get_class($this));
        foreach ($rf->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            assert($method instanceof \ReflectionMethod);
            if(substr($method->getName(), 0, 3) == 'get' && $method->getName() != 'get') {
                $values[] = $method->invoke($this);
            }
        }
        return $values;
    }
}
