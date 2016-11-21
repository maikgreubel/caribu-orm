<?php
namespace Nkey\Caribu\Orm;

trait OrmTypeUtil
{

    /**
     * Checks whether a given string equals identifier of a primitive type
     *
     * @param string $type
     *            The type identifier
     *            
     * @return bool true in case of string is identifier of primitive type, false otherwise
     */
    private static function isPrimitive(string $type): bool
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
     * Convert a value to a boolean value
     *
     * @param mixed $value            
     *
     * @return bool true or false
     */
    private static function boolval($value): bool
    {
        if (! function_exists('boolval')) {
            return (bool) $value;
        }
        return boolval($value);
    }
}