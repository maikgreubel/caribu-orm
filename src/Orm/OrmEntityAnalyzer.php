<?php
namespace Nkey\Caribu\Orm;

trait OrmEntityAnalyzer
{
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
        $pk = self::getAnnotatedPrimaryKey($class, $object, $onlyValue);

        if (null == $pk) {
            $pkCol = self::getPrimaryKeyCol($class);
            $method = sprintf("get%s", ucfirst($pkCol));

            try {
                $rfMethod = new \ReflectionMethod($class, $method);
                $pk = $rfMethod->invoke($object);
                if (!$onlyValue) {
                    $pk = array(
                        $pkCol => $pk
                    );
                }

            } catch (\ReflectionException $ex) {
                throw OrmException::fromPrevious($ex);
            }
        }

        return $pk;
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
        assert($instance instanceof Orm);

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