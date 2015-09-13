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
     * @param string $class The name of class of entity
     * @param \Nkey\Caribu\Model\AbstractModel $object The object where the primary key should be set
     * @param mixed $primaryKey The primary key value
     * @throws OrmException
     */
    private static function setPrimaryKey($class, $object, $primaryKey)
    {
        $pkCol = self::getAnnotatedPrimaryKeyProperty($class);
        if (null === $pkCol) {
            $pkCol = self::getPrimaryKeyCol($class);
        }
        $method = sprintf("set%s", ucfirst($pkCol));

        try {
            $rfMethod = new \ReflectionMethod($class, $method);
            $rfMethod->invoke($object, $primaryKey);
        } catch (\ReflectionException $ex) {
            throw OrmException::fromPrevious($ex);
        }
    }

    /**
     * Persist the mapped-by entities
     *
     * @param string $class The name of class of which the data has to be persisted
     * @param \Nkey\Caribu\Model\AbstractModel $object The entity which contain mapped-by entries to persist
     *
     * @throws OrmException
     */
    private static function persistMappedBy($class, \Nkey\Caribu\Model\AbstractModel $object)
    {
        $instance = self::getInstance();
        assert($instance instanceof Orm);

        $escapeSign = $instance->getDbType()->getEscapeSign();

        try {
            $rf = new \ReflectionClass($class);

            foreach ($rf->getProperties() as $property) {
                assert($property instanceof \ReflectionProperty);

                if (null !== ($parameters = self::getAnnotatedMappedByParameters($property->getDocComment()))) {
                    $mappedBy = self::parseMappedBy($parameters);

                    $method = sprintf("get%s", ucfirst($property->getName()));
                    $rfMethod = new \ReflectionMethod($class, $method);
                    assert($rfMethod instanceof \ReflectionMethod);
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

                        $query = sprintf(
                            "INSERT INTO %s%s%s (%s%s%s, %s%s%s) VALUES (:%s, :%s)",
                            $escapeSign,
                            $mappedBy['table'],
                            $escapeSign,
                            $escapeSign,
                            $mappedBy['inverseColumn'],
                            $escapeSign,
                            $escapeSign,
                            $mappedBy['column'],
                            $escapeSign,
                            $mappedBy['inverseColumn'],
                            $mappedBy['column']
                            );

                        $statement = null;
                        try {
                            $statement = $instance->startTX()->prepare($query);
                            $statement->bindValue(sprintf(':%s', $mappedBy['inverseColumn']), $ownPrimaryKey);
                            $statement->bindValue(sprintf(':%s', $mappedBy['column']), $foreignPrimaryKey);

                            $statement->execute();

                            $instance->commitTX();
                        } catch (\PDOException $ex) {
                            throw self::handleException(
                                $instance,
                                $statement,
                                $ex,
                                "Persisting related entities failed",
                                -1010
                                );
                        }
                    }
                }
            }
        } catch (\ReflectionException $ex) {
            throw OrmException::fromPrevious($ex);
        }
    }

    /**
     * Persist inner entity
     *
     * @param \ReflectionProperty $property The property which represents the inner entity
     * @param string $class The result class name
     * @param \Nkey\Caribu\Model\AbstractModel $object The object which holds the entity
     * @param string $namespace The result class namespace
     * @param boolean $persist Whether to persist
     *
     * @throws OrmException
     */
    private static function persistProperty(\ReflectionProperty $property, $class, $object, $namespace, $persist)
    {
        try {
        if (null !== ($type = self::getAnnotatedType($property->getDocComment(), $namespace)) &&
            !self::isPrimitive($type)) {
                if (!$persist && self::isCascadeAnnotated($property->getDocComment())) {
                    $persist = true;
                }

                $method = sprintf("get%s", ucfirst($property->getName()));
                $rfMethod = new \ReflectionMethod($class, $method);
                $entity = $rfMethod->invoke($object);
                if ($entity instanceof \Nkey\Caribu\Model\AbstractModel) {
                    if (!$persist && count($pk = self::getAnnotatedPrimaryKey($type, $entity))) {
                        list ($pkCol) = $pk;
                        if (is_empty($pk[$pkCol])) {
                            $persist = true;
                        }
                    }
                    if ($persist) {
                        $entity->persist();
                    }
                }
            }
        } catch (\ReflectionException $ex) {
            throw OrmException::fromPrevious($ex);
        }
    }

    /**
     * Persist the entity and all sub entities if necessary
     *
     * @param string $class The name of class of which the data has to be persisted
     * @param \Nkey\Caribu\Model\AbstractModel $object The entity to persist
     *
     * @throws OrmException
     */
    private static function persistAnnotated($class, $object)
    {
        try {
            $rf = new \ReflectionClass($class);

            foreach ($rf->getProperties() as $property) {
                self::persistProperty(
                    $property,
                    $class,
                    $object,
                    $rf->getNamespaceName(),
                    self::isCascadeAnnotated($rf->getDocComment())
                    );
            }
        } catch (\ReflectionException $ex) {
            throw OrmException::fromPrevious($ex);
        }
    }
}