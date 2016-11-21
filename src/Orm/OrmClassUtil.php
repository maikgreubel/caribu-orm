<?php
namespace Nkey\Caribu\Orm;

trait OrmClassUtil
{

    /**
     * Retrieve the properties from class
     *
     * @param string $class
     *            The name of class to get properties of
     *            
     * @return \ReflectionProperty[] Array of Reflection properties
     */
    private static function getClassProperties(string $class): array
    {
        $rf = new \ReflectionClass($class);
        
        return $rf->getProperties();
    }

    /**
     * Checks whether a type is an internal class defined by core or any extension
     *
     * @param string $type
     *            The type (class name) to check
     *            
     * @return bool
     */
    private static function isInternalClass(string $type): bool
    {
        try {
            $rf = new \ReflectionClass($type);
            return $rf->isInternal();
        } catch (\ReflectionException $exception) {
            // we do nothing and assume that the class is not checkable
        }
        return false;
    }

    /**
     * Build a full qualified class name including namespace
     *
     * @param string $ns
     *            The namespace of class
     * @param string $class
     *            The name of class
     *            
     * @return string The full qualified class name
     */
    private static function fullQualifiedName(string $ns, string $class): string
    {
        if (! class_exists($class)) {
            $class = sprintf("\\%s\\%s", $ns, $class);
        }
        return $class;
    }
}