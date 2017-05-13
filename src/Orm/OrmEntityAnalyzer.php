<?php
namespace Nkey\Caribu\Orm;

use Nkey\Caribu\Model\AbstractModel;

trait OrmEntityAnalyzer
{
    /**
     * Include mapping related functionality
     */
    use OrmMapping;

    /**
     * Get the name of table
     *
     * @param string $class
     *            The name of class
     *            
     * @return string The name of table
     *        
     * @throws OrmException
     */
    private static function getTableName(string $class): string
    {
        $parts = explode('\\', $class);
        $simpleClassName = end($parts);
        $tableName = strtolower($simpleClassName);
        $tableName = preg_replace('#model$#', '', $tableName);
        return self::getAnnotatedTableName($class, $tableName);
    }

    /**
     * Retrieve the primary key value
     *
     * @param string $class
     *            The name of class where to retrieve the primary key value
     *            
     * @param AbstractModel $object
     *            The object instance
     *            
     * @param bool $onlyValue
     *            Whether to retrieve only primary key value or both, value and column name
     *            
     * @return array Pair of column name and value of primary key or value only
     *        
     * @throws OrmException
     */
    private static function getPrimaryKey(string $class, AbstractModel $object, bool $onlyValue = false)
    {
        $primaryKey = self::getAnnotatedPrimaryKey($class, $object, $onlyValue);
        
        if (null !== $primaryKey) {
            return $primaryKey;
        }
        
        $pkCol = self::getPrimaryKeyCol($class);
        $method = sprintf("get%s", ucfirst($pkCol));
        
        try {
            $rfMethod = new \ReflectionMethod($class, $method);
            $primaryKey = $rfMethod->invoke($object);
            if (! $onlyValue) {
                $primaryKey = array(
                    $pkCol => $primaryKey
                );
            }
        } catch (\ReflectionException $exception) {
            throw OrmException::fromPrevious($exception);
        }
        
        return $primaryKey;
    }

    /**
     * Retrieve the name of column which represents the primary key
     *
     * @param string $class
     *            The name of class
     *            
     * @return string The name of primary key column
     *        
     * @throws OrmException
     */
    private static function getPrimaryKeyCol(string $class): string
    {
        $instance = self::getInstance();
        
        $pkColumn = self::getAnnotatedPrimaryKeyColumn($class);
        if ("" === $pkColumn) {
            $pkColumn = $instance->getDbType()->getPrimaryKeyColumn(self::getTableName($class), $instance);
        }
        if ("" === $pkColumn) {
            $pkColumn = 'id';
        }
        
        return $pkColumn;
    }
}
