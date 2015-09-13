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
        $rfClass = new \ReflectionClass(get_class($this));
        foreach ($rfClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass() != $rfClass) {
                continue;
            }
            if (substr($method->getName(), 0, 3) == 'get' && $method->getName() != 'get') {
                $values[] = $method->invoke($this);
            }
        }
        return $values;
    }
}
