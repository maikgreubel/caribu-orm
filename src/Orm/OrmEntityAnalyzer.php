<?php
namespace Nkey\Caribu\Orm;

trait OrmEntityAnalyzer
{
    /**
     * Include mapping related functionality
     */
    use OrmMapping;

    /**
     * Get the name of table
     *
     * @param string $class The name of class
     *
     * @return string The name of table
     *
     * @throws OrmException
     */
    private static function getTableName($class)
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
     * @param string $class The name of class where to retrieve the primary key value
     *
     * @return array Pair of column name and value of primary key
     *
     * @throws OrmException
     */
    private static function getPrimaryKey($class, $object, $onlyValue = false)
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
            if (!$onlyValue) {
                $primaryKey = array(
                    $pkCol => $primaryKey
                );
            }
        } catch (\ReflectionException $ex) {
            throw OrmException::fromPrevious($ex);
        }

        return $primaryKey;
    }

    /**
     * Retrieve the name of column which represents the primary key
     *
     * @param string $class The name of class
     *
     * @return string The name of primary key column
     *
     * @throws OrmException
     */
    private static function getPrimaryKeyCol($class)
    {
        $instance = self::getInstance();

        $pkColumn = self::getAnnotatedPrimaryKeyColumn($class);
        if (null === $pkColumn) {
            $pkColumn = $instance->getDbType()->getPrimaryKeyColumn(self::getTableName($class), $instance);
        }
        if (null === $pkColumn) {
            $pkColumn = 'id';
        }

        return $pkColumn;
    }
}
