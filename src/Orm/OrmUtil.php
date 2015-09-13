<?php
namespace Nkey\Caribu\Orm;

trait OrmUtil
{
    /**
     * Include exception handling related functionality
     */
    use OrmExceptionHandler;

    /**
     * Checks whether a given string equals identifier of a primitive type
     *
     * @param string $type The type identifier
     *
     * @return boolean true in case of string is identifier of primitive type, false otherwise
     */
    private static function isPrimitive($type)
    {
        $isPrimitive = false;

        switch ($type) {
            case 'int':
            case 'integer':
            case 'string':
            case 'boolean':
            case 'bool':
                $isPrimitive = true;
                break;
        }

        return $isPrimitive;
    }

    /**
     * Retrieve the properties from class
     *
     * @param string $class The name of class to get properties of
     *
     * @return \ReflectionProperty[] Array of Reflection properties
     */
    private static function getClassProperties($class)
    {
        $rf = new \ReflectionClass($class);

        return $rf->getProperties();
    }

    /**
     * Checks whether a type is an internal class defined by core or any extension
     *
     * @param string $type The type (class name) to check
     *
     * @return boolean
     */
    private static function isInternalClass($type)
    {
        try {
            $rf = new \ReflectionClass($type);
            return $rf->isInternal();
        } catch (\ReflectionException $ex) {
            // we do nothing and assume that the class is not checkable
        }
        return false;
    }

    /**
     * Convert a value to a boolean value
     *
     * @param mixed $value
     *
     * @return boolean true or false
     */
    private static function boolval($value)
    {
        if (!function_exists('boolval')) {
            return (bool)$value;
        }
        return boolval($value);
    }

    /**
     * Build a full qualified class name including namespace
     *
     * @param string $ns The namespace of class
     * @param string $class The name of class
     *
     * @return string The full qualified class name
     */
    private static function fullQualifiedName($ns, $class)
    {
        if (!class_exists($class)) {
            $class = sprintf("\\%s\\%s", $ns, $class);
        }
        return $class;
    }
}
