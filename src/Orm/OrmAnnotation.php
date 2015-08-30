<?php
namespace Nkey\Caribu\Orm;

use Nkey\Caribu\Model\AbstractModel;
use Nkey\Caribu\Orm\OrmException;

use \ReflectionClass;
use \ReflectionProperty;
use \ReflectionObject;
use \ReflectionException;
use \ReflectionMethod;

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
            $rf = new ReflectionClass($class);

            $docComments = $rf->getDocComment();

            $matches = array();
            if (preg_match('/@table (\w+)/', $docComments, $matches)) {
                $fallback = $matches[1];
            }

            return $fallback;
        } catch (ReflectionException $ex) {
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
            $rf = new ReflectionClass($class);

            $properties = $rf->getProperties();

            $properyName = null;

            foreach ($properties as $property) {
                assert($property instanceof ReflectionProperty);
                if (self::isIdAnnotated($property->getDocComment())) {
                    $properyName = $property->getName();
                    break;
                }
            }

            return $properyName;
        } catch (ReflectionException $ex) {
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
            $rf = new ReflectionClass($class);

            $properties = $rf->getProperties();

            $columnName = null;

            foreach ($properties as $property) {
                assert($property instanceof ReflectionProperty);
                $docComment = $property->getDocComment();
                if (self::isIdAnnotated($docComment)) {
                    if (null === ($columnName = self::getAnnotatedColumn($docComment))) {
                        $columnName = $property->getName();
                    }
                }
            }

            return $columnName;
        } catch (ReflectionException $ex) {
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
        $rf = new ReflectionClass($class);

        foreach ($rf->getProperties() as $property) {
            assert($property instanceof ReflectionProperty);

            $docComments = $property->getDocComment();

            $isDestinationProperty = false;

            if ($property->getName() == $propertyName) {
                $isDestinationProperty = true;
            }

            if (! $isDestinationProperty) {
                continue;
            }

            if (null !== ($type = self::getAnnotatedType($docComments, $rf->getNamespaceName()))) {
                break;
            }
        }

        return $type;
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
            $resultClass = new ReflectionClass($toClass);

            $rf = new ReflectionObject($from);

            $result = $resultClass->newInstanceWithoutConstructor();

            $properties = $rf->getProperties();
            foreach ($properties as $property) {
                assert($property instanceof ReflectionProperty);

                // attached property by annotation mapping => map later
                if (strpos($property->getName(), '.')) {
                    continue;
                }

                $value = $property->getValue($from);

                $type = self::getAnnotatedPropertyType($toClass, $property->getName());

                if (null !== $type && !self::isPrimitive($type) && !class_exists($type)) {
                    $type = sprintf("\\%s\\%s", $rf->getNamespaceName(), $type);
                }

                if (null !== $type && !self::isPrimitive($type) && class_exists($type)) {
                    $rfPropertyType = new ReflectionClass($type);
                    if (
                        $rfPropertyType->getParentClass() &&
                        strcmp($rfPropertyType->getParentClass()->name, 'Nkey\Caribu\Model\AbstractModel') == 0
                    ) {
                        $getById = new ReflectionMethod($type, "get");
                        $value = $getById->invoke(null, $value);
                    } else {
                        if ($rfPropertyType->isInternal()) {
                            $value = self::convertType($type, $value);
                        } else {
                            $value = $rfPropertyType->newInstance($value);
                        }
                    }
                }

                $method = sprintf("set%s", ucfirst($property->getName()));

                if ($resultClass->hasMethod($method)) {
                    $rfMethod = new ReflectionMethod($toClass, $method);
                    $rfMethod->invoke($result, self::convertType($type, $value));
                } else {
                    $resultClassProperties = $resultClass->getProperties();
                    foreach ($resultClassProperties as $resultClassProperty) {
                        assert($resultClassProperty instanceof ReflectionProperty);
                        $docComments = $resultClassProperty->getDocComment();

                        if (null !== ($destinationProperty = self::getAnnotatedColumn($docComments))) {
                            if ($destinationProperty == $property->getName()) {
                                $type = self::getAnnotatedType($docComments, $resultClass->getNamespaceName());
                                if (null !== $type) {
                                    if (!self::isPrimitive($type) && class_exists($type) && !$value instanceof $type) {
                                        continue 2;
                                    }
                                }

                                $method = sprintf("set%s", ucfirst($resultClassProperty->getName()));
                                if ($resultClass->hasMethod($method)) {
                                    $rfMethod = new ReflectionMethod($toClass, $method);
                                    $rfMethod->invoke($result, $value);
                                    continue 2;
                                }
                            }
                        }
                    }
                }
            }

            return $result;
        } catch (ReflectionException $ex) {
            throw OrmException::fromPrevious($ex);
        }
    }

    /**
     * Persist the entity and all sub entities if necessary
     *
     * @param string $class The name of class of which the data has to be persisted
     * @param AbstractModel $object The entity to persist
     *
     * @throws OrmException
     */
    private static function persistAnnotated($class, $object)
    {
        try {
            $rf = new ReflectionClass($class);

            $persist = false;

            if (self::isCascadeAnnotated($rf->getDocComment())) {
                $persist = true;
            }

            foreach ($rf->getProperties() as $property) {
                assert($property instanceof ReflectionProperty);

                if (null !== ($type = self::getAnnotatedType($property->getDocComment(), $rf->getNamespaceName()))) {
                    if (!self::isPrimitive($type)) {
                        if (! $persist) {
                            if (self::isCascadeAnnotated($property->getDocComment())) {
                                $persist = true;
                            }
                        }

                        $method = sprintf("get%s", ucfirst($property->getName()));
                        $rfMethod = new ReflectionMethod($class, $method);
                        $entity = $rfMethod->invoke($object);
                        if ($entity instanceof AbstractModel) {
                            if (! $persist) {
                                $pk = self::getAnnotatedPrimaryKey($type, $entity);
                                if (is_array($pk)) {
                                    list ($pkCol) = $pk;
                                    if (is_empty($pk[$pkCol])) {
                                        $persist = true;
                                    }
                                }
                            }
                            if ($persist) {
                                $entity->persist();
                            }
                        }
                    }
                }
            }
        } catch (ReflectionException $ex) {
            throw OrmException::fromPrevious($ex);
        }
    }

    /**
     * Retrieve list of columns and its corresponding pairs
     *
     * @param string $class The name of class to retrieve all column-value pairs of
     * @param AbstractModel $object The entity to get the column-value pairs of
     *
     * @return array List of column => value pairs
     *
     * @throws OrmException
     */
    private static function getAnnotatedColumnValuePairs($class, $object)
    {
        $pairs = array();
        try {
            $rf = new ReflectionClass($class);
            $properties = $rf->getProperties();

            foreach ($properties as $property) {
                assert($property instanceof ReflectionProperty);


                $docComments = $property->getDocComment();

                // mapped by entries have no corresponding table column, so we skip it here
                if (preg_match('/@mappedBy/i', $docComments)) {
                    continue;
                }
                if (null === ($column = self::getAnnotatedColumn($docComments))) {
                    $column = $property->getName();
                }

                $method = sprintf("get%s", ucfirst($property->getName()));
                $rfMethod = new ReflectionMethod($class, $method);

                $value = $rfMethod->invoke($object);
                if (null != $value) {
                    $pairs[$column] = $value;
                }
            }
        } catch (ReflectionException $ex) {
            throw OrmException::fromPrevious($ex);
        }

        return $pairs;
    }

    /**
     * Retrieve the primary key name and value using annotation
     *
     * @param string $class The name of class to retrieve the primary key name and value
     * @param AbstractModel The entity to retrieve the pimary key value
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
            $rf = new ReflectionClass($class);

            $properties = $rf->getProperties();

            foreach ($properties as $property) {
                assert($property instanceof ReflectionProperty);
                $docComment = $property->getDocComment();
                if (self::isIdAnnotated($docComment)) {
                    $method = sprintf("get%s", ucfirst($property->getName()));
                    $rfMethod = new ReflectionMethod($class, $method);

                    if (null === ($columnName = self::getAnnotatedColumn($docComment))) {
                        $columnName = $property->getName();
                    }

                    $pk = $rfMethod->invoke($object);

                    if (! $onlyValue) {
                        $pk = array(
                            $columnName => $pk
                        );
                    }
                    break;
                }
            }
        } catch (ReflectionException $ex) {
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
                $type = sprintf("\\%s\\%s", $namespace, $type);
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
        $rfProperty = new ReflectionProperty($class, $property);
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
     * Parse a complex crition into simple criterion
     *
     * @param string $criterion The full criterion pattern
     *
     * @return string The simple criterion name
     */
    private static function getSimpleCriterionName($criterion)
    {
        $criterion = str_ireplace('OR ', '', $criterion);
        if (strpos($criterion, '.')) {
            list($criterion) = explode('.', $criterion);
        }
        return $criterion;
    }

    /**
     * When criterion is a property but annotated column name differs, we take the column name
     *
     * @param string $className The entity class name
     * @param string $criterion The criterion
     */
    private static function getAnnotatedCriterion($className, $criterion)
    {
        $class = new ReflectionClass($className);
        $simpleCriterion = self::getSimpleCriterionName($criterion);
        if ($class->hasProperty($simpleCriterion)) {
            $property = $class->getProperty($simpleCriterion);
            if (strcmp($criterion, $simpleCriterion) == 0) {
                $column = self::getAnnotatedColumn($property->getDocComment());
                if (null !== $column) {
                    $criterion = str_replace($simpleCriterion, $column, $criterion);
                }
            }
        }

        return $criterion;
    }

    /**
     * Get the annotated join query
     *
     * @param string $class The name of class to use as left class
     * @param string $table The name of table
     * @param array $criteria
     *            (by reference) List of criterias
     * @param array $columns
     *            (by reference) List of columns
     * @param string $escapeSign The character which escapes special literals
     *
     * @return string The join query sql statment
     *
     * @throws OrmException
     */
    private static function getAnnotatedQuery($class, $table, &$criteria, &$columns, $escapeSign)
    {
        $joinQuery = "";

        $rf = new ReflectionClass($class);

        $replacedCriteria = array();

        // Example criterion: user.name => 'john'
        foreach (array_keys($criteria) as $criterion) {
            $replacedCriteria[self::getAnnotatedCriterion($class, $criterion)] = $criteria[$criterion];
            // from example criterionProperty will be 'name', criterion will now be 'user'
            if (strpos($criterion, '.') !== false) {
                list ($criterion) = explode('.', $criterion);
            }

            // class must have a property named by criterion
            $rfProperty = $rf->hasProperty($criterion) ? $rf->getProperty($criterion) : null;
            if ($rfProperty == null) {
                continue;
            }

            // check annotations
            $propertyClass = "";
            // search the type of property value
            if (null !== ($type = self::getAnnotatedType($rfProperty->getDocComment(), $rf->getNamespaceName()))) {
                if (!self::isPrimitive($type) && class_exists($type)) {
                    $propertyClass = $type;
                }
            }
            $inverseTable = $propertyClass ? self::getAnnotatedTableName($propertyClass, $criterion) : $criterion;

            // search the table mapping conditions
            if (null !== ($parameters = self::getAnnotatedMappedByParameters($rfProperty->getDocComment()))) {
                $mappedBy = self::parseMappedBy($parameters);

                $pkCol = self::getAnnotatedPrimaryKeyColumn($class);
                $inversePkCol = self::getAnnotatedPrimaryKeyColumn($propertyClass);

                $joinQuery = sprintf(
                    "JOIN %s%s%s ON %s%s%s.%s%s%s = %s%s%s.%s%s%s ",
                    $escapeSign, $mappedBy['table'], $escapeSign,
                    $escapeSign, $mappedBy['table'], $escapeSign,
                    $escapeSign, $mappedBy['inverseColumn'], $escapeSign,
                    $escapeSign, $table, $escapeSign,
                    $escapeSign, $pkCol, $escapeSign
                );
                $joinQuery .= sprintf(
                    "JOIN %s%s%s ON %s%s%s.%s%s%s = %s%s%s.%s%s%s",
                    $escapeSign, $inverseTable, $escapeSign,
                    $escapeSign, $inverseTable, $escapeSign,
                    $escapeSign, $inversePkCol, $escapeSign,
                    $escapeSign, $mappedBy['table'], $escapeSign,
                    $escapeSign, $mappedBy['column'], $escapeSign
                );

                $columns[] = sprintf("%s%s%s.%s%s%s AS '%s.%s'",
                    $escapeSign, $inverseTable, $escapeSign,
                    $escapeSign, $inversePkCol, $escapeSign,
                    $inverseTable, $inversePkCol);
            } elseif ($propertyClass != "") {
                $inversePkCol = self::getAnnotatedPrimaryKeyColumn($propertyClass);
                $column = self::getAnnotatedColumnFromProperty($class, $rfProperty->getName());
                $joinQuery = sprintf(
                    "JOIN %s%s%s AS %s%s%s ON %s%s%s.%s%s%s = %s%s%s.%s%s%s",
                    $escapeSign, $inverseTable, $escapeSign,
                    $escapeSign, $criterion, $escapeSign,
                    $escapeSign, $criterion, $escapeSign,
                    $escapeSign, $inversePkCol, $escapeSign,
                    $escapeSign, $table, $escapeSign,
                    $escapeSign, $column, $escapeSign
                );
                $columns[] = sprintf("%s%s%s.%s%s%s AS '%s.%s'",
                    $escapeSign, $criterion, $escapeSign,
                    $escapeSign, $inversePkCol, $escapeSign,
                    $criterion, $inversePkCol);
            }
        }
        $criteria = $replacedCriteria;

        return $joinQuery;
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
            $rf = new ReflectionClass($class);
            if (preg_match('/@eager/', $rf->getDocComment())) {
                $eager = true;
            }
        } catch (ReflectionException $ex) {
            throw OrmException::fromPrevious($ex);
        }
        return $eager;
    }

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
}
