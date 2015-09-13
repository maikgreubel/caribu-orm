<?php
namespace Nkey\Caribu\Orm;

trait OrmMapping
{
    use OrmAnnotation;

    /**
     * Map a object from default class into specific
     *
     * @param stdClass $from The unmapped data as stdClass object
     * @param string $toClass The name of class to map data into
     *
     * @return object The new created object of $toClass containing the mapped data
     *
     * @throws OrmException
     * @throws PDOException
     */
    private static function map($from, $toClass, Orm $orm)
    {
        $result = null;
        try {
            $result = self::mapAnnotated($from, $toClass);
            self::mapReferenced($from, $toClass, $result);
            if (self::isEager($toClass)) {
                self::injectMappedBy($toClass, $result, $orm);
            }
        } catch (OrmException $ex) {
            // TODO: implement simple handling without annotation
            throw $ex;
        }

        return $result;
    }

    /**
     * Map a referenced object into current mapped object
     *
     * @param object $from The unmapped object as stdClass
     * @param string $toClass The name of class where the mapped data will be stored into
     * @param AbstractModel $result The mapped entity
     */
    private static function mapReferenced($from, $toClass, $result)
    {
        try {
            $rfToClass = new \ReflectionClass($toClass);

            foreach (get_object_vars($from) as $property => $value) {
                if (!strpos($property, '.')) {
                    continue;
                }

                list($toProperty, $column) = explode('.', $property);

                if (!$rfToClass->hasProperty($toProperty)) {
                    continue;
                }

                $referencedClass = self::getAnnotatedPropertyType($toClass, $toProperty);

                if (!class_exists($referencedClass)) {
                    $referencedClass = sprintf("\\%s\\%s", $rfToClass->getNamespaceName(), $referencedClass);
                }

                $rfReferenced = new \ReflectionClass($referencedClass);

                $findMethod = $rfReferenced->getMethod("find");
                $referencedObject = $findMethod->invoke(null, array($column => $value));

                $propertySetter = $rfToClass->getMethod(sprintf("set%s", ucfirst($toProperty)));

                $propertySetter->invoke($result, $referencedObject);
            }
        } catch (\ReflectionException $ex) {
            throw OrmException::fromPrevious($ex);
        }
    }

    /**
     * Inject the mappedBy annotated properties
     *
     * @param string $toClass The class of entity
     * @param AbstractModel $object Prefilled entity
     *
     * @throws OrmException
     * @throws PDOException
     */
    private static function injectMappedBy($toClass, &$object, Orm $orm)
    {
        try {
            $rfToClass = new \ReflectionClass($toClass);

            foreach ($rfToClass->getProperties() as $property) {
                if (null === ($parameters = self::getAnnotatedMappedByParameters($property->getDocComment()))) {
                    continue;
                }

                $mappedBy = self::parseMappedBy($parameters);

                $type = self::getAnnotatedType($property->getDocComment(), $rfToClass->getNamespaceName());

                if (null === $type) {
                    throw new OrmException(
                        "Can't use mappedBy without specific type for property {property}",
                        array('property' => $property->getName())
                    );
                }

                if (self::isPrimitive($type)) {
                    throw new OrmException(
                        "Primitive type can not be used in mappedBy for property {property}",
                        array('property' => $property->getName())
                    );
                }

                $getMethod = new \ReflectionMethod($toClass, sprintf("get%s", ucfirst($property->getName())));
                if ($getMethod->invoke($object)) {
                    continue;
                }

                $ownPrimaryKey = self::getPrimaryKey($toClass, $object, true);

                $otherTable = self::getTableName($type);
                $otherPrimaryKeyName = self::getPrimaryKeyCol($type);
                $ownPrimaryKeyName = self::getPrimaryKeyCol($toClass);

                $query = sprintf(
                    "SELECT %s.* FROM %s
                        JOIN %s ON %s.%s = %s.%s
                        WHERE %s.%s = :%s",
                    $otherTable,
                    $otherTable,
                    $mappedBy['table'],
                    $mappedBy['table'],
                    $mappedBy['column'],
                    $otherTable,
                    $otherPrimaryKeyName,
                    $mappedBy['table'],
                    $mappedBy['inverseColumn'],
                    $ownPrimaryKeyName
                );

                $statement = null;

                try {
                    $statement = $orm->startTX()->prepare($query);
                    $statement->bindValue(sprintf(":%s", $ownPrimaryKeyName), $ownPrimaryKey);

                    $statement->execute();

                    $result = $statement->fetch(\PDO::FETCH_OBJ);

                    if (false == $result) {
                        throw new OrmException(
                            "No foreign entity found for {entity} using primary key {pk}",
                            array('entity' => $toClass, 'pk' => $$ownPrimaryKey)
                        );
                    }

                    $orm->commitTX();

                    $setMethod = new \ReflectionMethod($toClass, sprintf("set%s", ucfirst($property->getName())));

                    $setMethod->invoke($object, self::map($result, $type, $orm));
                } catch (\PDOException $ex) {
                    throw self::handleException($orm, $statement, $ex, "Mapping failed", - 1010);
                }
            }
        } catch (\ReflectionException $ex) {
            throw OrmException::fromPrevious($ex);
        }
    }

    /**
     * Map default class object into specific by annotation
     *
     * @param object $from The unmapped dataset
     * @param string $toClass The name of class where to map data in
     *
     * @return AbstractModel The mapped data as entity
     *
     * @throws OrmException
     */
    private static function mapAnnotated($from, $toClass)
    {
        try {
            $resultClass = new \ReflectionClass($toClass);

            $rf = new \ReflectionObject($from);

            $result = $resultClass->newInstanceWithoutConstructor();

            $properties = $rf->getProperties();
            foreach ($properties as $property) {
                // attached property by annotation mapping => map later
                if (strpos($property->getName(), '.')) {
                    continue;
                }

                list($type, $value) = self::getAnnotatedPropertyValue(
                    $from,
                    $toClass,
                    $property,
                    $rf->getNamespaceName()
                );

                $result = self::assignPropertyValue($result, $resultClass, $property->getName(), $type, $value);
            }

            return $result;
        } catch (\ReflectionException $ex) {
            throw OrmException::fromPrevious($ex);
        }
    }

    /**
     * Assign the property value to result object
     *
     * @param object $result
     * @param \ReflectionClass $resultClass
     * @param string $propertyName
     * @param string $type
     * @param mixed $value
     *
     * @return object The assigned result object
     */
    private static function assignPropertyValue($result, \ReflectionClass $resultClass, $propertyName, $type, $value)
    {
        $method = sprintf("set%s", ucfirst($propertyName));

        if ($resultClass->hasMethod($method)) {
            $rfMethod = new \ReflectionMethod($resultClass->getName(), $method);
            $rfMethod->invoke($result, self::convertType($type, $value));
        } else {
            $resultClassProperties = $resultClass->getProperties();
            foreach ($resultClassProperties as $resultClassProperty) {
                $docComments = $resultClassProperty->getDocComment();

                if (null === ($destinationProperty = self::getAnnotatedColumn($docComments))) {
                    continue;
                }

                if ($destinationProperty !== $propertyName) {
                    continue;
                }

                $type = self::getAnnotatedType($docComments, $resultClass->getNamespaceName());
                if (null === $type) {
                    continue;
                }

                if (!self::isPrimitive($type) && class_exists($type) && !$value instanceof $type) {
                    break;
                }

                $method = sprintf("set%s", ucfirst($resultClassProperty->getName()));
                if ($resultClass->hasMethod($method)) {
                    $rfMethod = new \ReflectionMethod($resultClass->getName(), $method);
                    $rfMethod->invoke($result, $value);
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * Parse the @mappedBy annotation
     *
     * @param string $mappedBy The mappedBy annotation string
     *
     * @return array All parsed property attributes of the mappedBy string
     */
    private static function parseMappedBy($mappedBy)
    {
        $mappingOptions = array();
        foreach (explode(',', $mappedBy) as $mappingOption) {
            list ($option, $value) = preg_split('/=/', $mappingOption);
            $mappingOptions[$option] = $value;
        }

        return $mappingOptions;
    }
}
