<?php
namespace Nkey\Caribu\Orm;

/**
 * Persisting provider for Caribu Orm
 *
 * This class is part of Caribu package
 *
 * @author Maik Greubel <greubel@nkey.de>
 */
trait OrmPersister
{
    /**
     * Include statement related functionality
     */
    use OrmStatement;

    /**
     * Set the primary key value after persist
     *
     * @param string $class
     *            The name of class of entity
     * @param \Nkey\Caribu\Model\AbstractModel $object
     *            The object where the primary key should be set
     * @param mixed $primaryKey
     *            The primary key value
     * @throws OrmException
     */
    private static function setPrimaryKey($class, $object, $primaryKey)
    {
        $pkCol = self::getAnnotatedPrimaryKeyProperty($class);
        if ("" === $pkCol) {
            $pkCol = self::getPrimaryKeyCol($class);
        }
        $method = sprintf("set%s", ucfirst($pkCol));
        
        try {
            $rfMethod = new \ReflectionMethod($class, $method);
            $rfMethod->invoke($object, $primaryKey);
        } catch (\ReflectionException $exception) {
            throw OrmException::fromPrevious($exception);
        }
    }

    /**
     * Persist the mapped-by entities
     *
     * @param string $class
     *            The name of class of which the data has to be persisted
     * @param \Nkey\Caribu\Model\AbstractModel $object
     *            The entity which contain mapped-by entries to persist
     *            
     * @throws OrmException
     */
    private static function persistMappedBy(string $class, \Nkey\Caribu\Model\AbstractModel $object)
    {
        $instance = self::getInstance();
        $escapeSign = $instance->getDbType()->getEscapeSign();
        
        try {
            $rf = new \ReflectionClass($class);
            
            foreach ($rf->getProperties() as $property) {
                if ("" !== ($parameters = self::getAnnotatedMappedByParameters($property->getDocComment()))) {
                    $mappedBy = self::parseMappedBy($parameters);
                    
                    $rfMethod = new \ReflectionMethod($class, sprintf("get%s", ucfirst($property->getName())));
                    $foreignEntity = $rfMethod->invoke($object);
                    
                    if (null !== $foreignEntity) {
                        $foreignPrimaryKey = self::getPrimaryKey(get_class($foreignEntity), $foreignEntity, true);
                        $ownPrimaryKey = self::getPrimaryKey($class, $object, true);
                        
                        if (is_null($foreignPrimaryKey)) {
                            throw new OrmException("No primary key column for foreign key found!");
                        }
                        if (is_null($ownPrimaryKey)) {
                            throw new OrmException("No primary key column found!");
                        }
                        
                        $query = sprintf("INSERT INTO %s%s%s (%s%s%s, %s%s%s) VALUES (:%s, :%s)", $escapeSign, $mappedBy['table'], $escapeSign, $escapeSign, $mappedBy['inverseColumn'], $escapeSign, $escapeSign, $mappedBy['column'], $escapeSign, $mappedBy['inverseColumn'], $mappedBy['column']);
                        
                        $statement = null;
                        try {
                            $statement = $instance->startTX()->prepare($query);
                            $statement->bindValue(sprintf(':%s', $mappedBy['inverseColumn']), $ownPrimaryKey);
                            $statement->bindValue(sprintf(':%s', $mappedBy['column']), $foreignPrimaryKey);
                            
                            $statement->execute();
                            
                            $instance->commitTX();
                        } catch (\PDOException $exception) {
                            throw self::handleException($instance, $statement, $exception, "Persisting related entities failed", - 1010);
                        }
                    }
                }
            }
        } catch (\ReflectionException $exception) {
            throw OrmException::fromPrevious($exception);
        }
    }

    /**
     * Persist inner entity
     *
     * @param \ReflectionProperty $property
     *            The property which represents the inner entity
     * @param string $class
     *            The result class name
     * @param \Nkey\Caribu\Model\AbstractModel $object
     *            The object which holds the entity
     * @param string $namespace
     *            The result class namespace
     * @param bool $persist
     *            Whether to persist
     *            
     * @throws OrmException
     */
    private static function persistProperty(\ReflectionProperty $property, string $class, \Nkey\Caribu\Model\AbstractModel $object, string $namespace, bool $persist)
    {
        try {
            if ("" !== ($type = self::getAnnotatedType($property->getDocComment(), $namespace)) && ! self::isPrimitive($type)) {
                if (! $persist && self::isCascadeAnnotated($property->getDocComment())) {
                    $persist = true;
                }
                
                $rfMethod = new \ReflectionMethod($class, sprintf("get%s", ucfirst($property->getName())));
                $entity = $rfMethod->invoke($object);
                if ($entity instanceof \Nkey\Caribu\Model\AbstractModel) {
                    if (! $persist && count($pk = self::getAnnotatedPrimaryKey($type, $entity, false))) {
                        list ($pkCol) = $pk;
                        if (!isset($pk[$pkCol]) || empty($pk[$pkCol])) {
                            $persist = true;
                        }
                    }
                    if ($persist) {
                        $entity->persist();
                    }
                }
            }
        } catch (\ReflectionException $exception) {
            throw OrmException::fromPrevious($exception);
        }
    }

    /**
     * Persist the entity and all sub entities if necessary
     *
     * @param string $class
     *            The name of class of which the data has to be persisted
     * @param \Nkey\Caribu\Model\AbstractModel $object
     *            The entity to persist
     *            
     * @throws OrmException
     */
    private static function persistAnnotated(string $class, \Nkey\Caribu\Model\AbstractModel $object)
    {
        try {
            $rfClass = new \ReflectionClass($class);
            
            foreach ($rfClass->getProperties() as $property) {
                self::persistProperty($property, $class, $object, $rfClass->getNamespaceName(), self::isCascadeAnnotated($rfClass->getDocComment()));
            }
        } catch (\ReflectionException $exception) {
            throw OrmException::fromPrevious($exception);
        }
    }
}
