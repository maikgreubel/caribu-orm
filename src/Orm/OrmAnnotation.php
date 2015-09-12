<?php
namespace Nkey\Caribu\Orm;

/**
 * Annotation provider for Caribu Orm
 *
 * This class is part of Caribu package
 *
 * @author Maik Greubel <greubel@nkey.de>
 */
trait OrmAnnotation
{
    use OrmDataTypeConverter;

    /**
     * Retrieve the annotated table name
     *
     * @param string $class The name of class
     * @param string $fallback As fallback if nothing was found
     *
     * @return string The name of table
     *
     * @throws OrmException
     */
    private static function getAnnotatedTableName($class, $fallback)
    {
        try {
            $rf = new \ReflectionClass($class);

            $docComments = $rf->getDocComment();

            $matches = array();
            if (preg_match('/@table (\w+)/', $docComments, $matches)) {
                $fallback = $matches[1];
            }

            return $fallback;
        } catch (\ReflectionException $ex) {
            throw OrmException::fromPrevious($ex);
        }
    }

    /**
     * Get the annotated primary key property name
     *
     * The property is annotated with the @id annotation
     *
     * @param string $class The name of class to retrieve the primary key property
     *
     * @return string The name of property which represents the primary key
     *
     * @throws OrmException
     */
    private static function getAnnotatedPrimaryKeyProperty($class)
    {
        try {
            $propertyName = null;

            foreach (self::getClassProperties($class) as $property) {
                assert($property instanceof \ReflectionProperty);
                if (self::isIdAnnotated($property->getDocComment())) {
                    $propertyName = $property->getName();
                    break;
                }
            }

            return $propertyName;
        } catch (\ReflectionException $ex) {
            throw OrmException::fromPrevious($ex);
        }
    }

    /**
     * Get the annotated primary key
     *
     * The property is annotated with the @id annotation
     * The propery may have a @column annotation to modify the database column name
     *
     * @param string $class The name of class to retrieve the primary key column of
     *
     * @return string|null The name of primary key column
     *
     * @throws OrmException
     */
    private static function getAnnotatedPrimaryKeyColumn($class)
    {
        try {
            $columnName = null;

            foreach (self::getClassProperties($class) as $property) {
                assert($property instanceof \ReflectionProperty);
                $docComment = $property->getDocComment();
                if (self::isIdAnnotated($docComment)) {
                    if (null === ($columnName = self::getAnnotatedColumn($docComment))) {
                        $columnName = $property->getName();
                    }
                }
            }

            return $columnName;
        } catch (\ReflectionException $ex) {
            throw OrmException::fromPrevious($ex);
        }
    }

    /**
     * Get the property type via annotation
     *
     * @param string $class The name of class to retrieve a particular property type
     * @param string $propertyName The name of property to retrieve the type of
     *
     * @return string|null The property type
     */
    private static function getAnnotatedPropertyType($class, $propertyName)
    {
        $type = null;
        $rf = new \ReflectionClass($class);

        foreach ($rf->getProperties() as $property) {
            assert($property instanceof \ReflectionProperty);

            $docComments = $property->getDocComment();

            $isDestinationProperty = false;

            if ($property->getName() == $propertyName) {
                $isDestinationProperty = true;
            }

            if (!$isDestinationProperty) {
                continue;
            }

            if (null !== ($type = self::getAnnotatedType($docComments, $rf->getNamespaceName()))) {
                break;
            }
        }

        return $type;
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
        return sprintf("\\%s\\%s", $ns, $class);
    }

    /**
     * Get the value from property
     *
     * @param object $from The source object
     * @param string $toClass The type of destination class
     * @param \ReflectionProperty $property The property to get value of
     * @param string $namespace The namespace of destination class
     *
     * @return array The type and value from property
     */
    private static function getAnnotatedPropertyValue($from, $toClass, \ReflectionProperty $property, $namespace)
    {
        $value = $property->getValue($from);

        $type = self::getAnnotatedPropertyType($toClass, $property->getName());

        if (null !== $type && !self::isPrimitive($type) && !class_exists($type)) {
            $type = self::fullQualifiedName($namespace, $type);
        }

        if (null === $type || self::isPrimitive($type) || !class_exists($type)) {
            return array($type, $value);
        }

        $rfPropertyType = new \ReflectionClass($type);

        if ($rfPropertyType->getParentClass() &&
            strcmp($rfPropertyType->getParentClass()->name, 'Nkey\Caribu\Model\AbstractModel') == 0
            ) {
            $getById = new \ReflectionMethod($type, "get");
            $value = $getById->invoke(null, $value);

            return array($type, $value);
        }

        $value = $rfPropertyType->isInternal() ?
            self::convertType($type, $value) : $rfPropertyType->newInstance($value);

        return array($type, $value);
    }

    /**
     * Retrieve list of columns and its corresponding pairs
     *
     * @param string $class The name of class to retrieve all column-value pairs of
     * @param \Nkey\Caribu\Model\AbstractModel $object The entity to get the column-value pairs of
     *
     * @return array List of column => value pairs
     *
     * @throws OrmException
     */
    private static function getAnnotatedColumnValuePairs($class, $object)
    {
        $pairs = array();
        try {
            $rf = new \ReflectionClass($class);
            $properties = $rf->getProperties();

            foreach ($properties as $property) {
                assert($property instanceof \ReflectionProperty);


                $docComments = $property->getDocComment();

                // mapped by entries have no corresponding table column, so we skip it here
                if (preg_match('/@mappedBy/i', $docComments)) {
                    continue;
                }
                if (null === ($column = self::getAnnotatedColumn($docComments))) {
                    $column = $property->getName();
                }

                $method = sprintf("get%s", ucfirst($property->getName()));
                $rfMethod = new \ReflectionMethod($class, $method);

                $value = $rfMethod->invoke($object);
                if (null != $value) {
                    $pairs[$column] = $value;
                }
            }
        } catch (\ReflectionException $ex) {
            throw OrmException::fromPrevious($ex);
        }

        return $pairs;
    }

    /**
     * Retrieve the primary key name and value using annotation
     *
     * @param string $class The name of class to retrieve the primary key name and value
     * @param \Nkey\Caribu\Model\AbstractModel $object The entity to retrieve the pimary key value
     * @param boolean $onlyValue Whether to retrieve only the value instead of name and value
     *
     * @return array The "name" => "value" of primary key or only the value (depending on $onlyValue)
     *
     * @throws OrmException
     */
    private static function getAnnotatedPrimaryKey($class, $object, $onlyValue = false)
    {
        $pk = null;
        try {
            $rf = new \ReflectionClass($class);

            $properties = $rf->getProperties();

            foreach ($properties as $property) {
                assert($property instanceof \ReflectionProperty);
                $docComment = $property->getDocComment();
                if (self::isIdAnnotated($docComment)) {
                    $method = sprintf("get%s", ucfirst($property->getName()));
                    $rfMethod = new \ReflectionMethod($class, $method);

                    if (null === ($columnName = self::getAnnotatedColumn($docComment))) {
                        $columnName = $property->getName();
                    }

                    $pk = $rfMethod->invoke($object);

                    if (!$onlyValue) {
                        $pk = array(
                            $columnName => $pk
                        );
                    }
                    break;
                }
            }
        } catch (\ReflectionException $ex) {
            throw OrmException::fromPrevious($ex);
        }

        return $pk;
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

    /**
     * Get the annotated type
     *
     * @param string $comment The document comment string which may contain the @var annotation
     * @param string $namespace Optional namespace where class is part of
     *
     * @return string The parsed type
     *
     * @throws OrmException
     */
    private static function getAnnotatedType($comment, $namespace = null)
    {
        $type = null;
        $matches = array();
        if (preg_match('/@var ([\w\\\\]+)/', $comment, $matches)) {
            $originType = $type = $matches[1];

            if (self::isPrimitive($type)) {
                return $type;
            }

            if (!class_exists($type) && !strchr($type, "\\") && $namespace !== null) {
                $type = self::fullQualifiedName($namespace, $type);
            }

            if (!class_exists($type)) {
                throw new OrmException("Annotated type {type} could not be found nor loaded", array(
                    'type' => $originType
                ));
            }
        }
        return $type;
    }

    /**
     * Get the annotated column name
     *
     * @param string $class The name of class to retrieve te annotated column name
     * @param string $property The property which is annotated by column name
     *
     * @return string The column name
     */
    private static function getAnnotatedColumnFromProperty($class, $property)
    {
        $rfProperty = new \ReflectionProperty($class, $property);
        return self::getAnnotatedColumn($rfProperty->getDocComment());
    }

    /**
     * Get the annotated column name from document comment string
     *
     * @param string $comment The document comment which may contain the @column annotation
     *
     * @return string|null The parsed column name
     */
    private static function getAnnotatedColumn($comment)
    {
        $columnName = null;

        $matches = array();
        if (preg_match("/@column (\w+)/", $comment, $matches)) {
            $columnName = $matches[1];
        }
        return $columnName;
    }

    /**
     * Check whether property is annotated using @id
     *
     * @param string $comment The document comment which may contain the @id annotation
     *
     * @return boolean true in case of it is annotated, false otherwise
     */
    private static function isIdAnnotated($comment)
    {
        $isId = false;

        if (preg_match('/@id/', $comment)) {
            $isId = true;
        }

        return $isId;
    }

    /**
     * Check whether property is annotated using @cascade
     *
     * @param string $comment The document comment which may contain the @cascade annotation
     *
     * @return boolean true in case of it is annotated, false otherwise
     */
    private static function isCascadeAnnotated($comment)
    {
        $isCascade = false;

        if (preg_match('/@cascade/', $comment)) {
            $isCascade = true;
        }

        return $isCascade;
    }

    /**
     * Get the mappedBy parameters from documentation comment
     *
     * @param string  $comment The documentation comment to parse
     *
     * @return string The parsed parameters or null
     */
    private static function getAnnotatedMappedByParameters($comment)
    {
        $parameters = null;

        $matches = array();
        if (preg_match('/@mappedBy\(([^\)].+)\)/', $comment, $matches)) {
            $parameters = $matches[1];
        }
        return $parameters;
    }

    /**
     * Checks whether an entity has eager fetch type
     *
     * @param string $class Name of class of entity
     *
     * @return boolean true if fetch type is eager, false otherwise
     *
     * @throws OrmException
     */
    private static function isEager($class)
    {
        $eager = false;
        try {
            $rf = new \ReflectionClass($class);
            if (preg_match('/@eager/', $rf->getDocComment())) {
                $eager = true;
            }
        } catch (\ReflectionException $ex) {
            throw OrmException::fromPrevious($ex);
        }
        return $eager;
    }
}
