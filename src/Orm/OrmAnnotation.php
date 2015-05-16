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

    /**
     * Retrieve the annotated table name
     *
     * @param string $class
     * @param string $fallback
     * @return string
     *
     * @throws OrmException
     */
    private function getAnnotatedTableName($class, $fallback)
    {
        try {
            $rf = new ReflectionClass($class);

            $docComments = $rf->getDocComment();

            $matches = array();
            if ($docComments && preg_match('/@table (\w+)/', $docComments, $matches) && count($matches) > 1) {
                array_shift($matches);
                $fallback = $matches[0];
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
     * @param string $class
     * @return string
     *
     * @throws OrmException
     */
    private function getAnnotatedPrimaryKeyProperty($class)
    {
        try {
            $rf = new ReflectionClass($class);

            $properties = $rf->getProperties();

            $properyName = null;

            foreach ($properties as $property) {
                assert($property instanceof ReflectionProperty);
                $docComment = $property->getDocComment();
                if ($docComment && preg_match('/@id/', $docComment)) {
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
     * @param string $class
     * @return string
     *
     * @throws OrmException
     */
    private function getAnnotatedPrimaryKeyColumn($class)
    {
        try {
            $rf = new ReflectionClass($class);

            $properties = $rf->getProperties();

            $columnName = null;

            foreach ($properties as $property) {
                assert($property instanceof ReflectionProperty);
                $docComment = $property->getDocComment();
                if ($docComment && preg_match('/@id/', $docComment)) {
                    $columnName = $property->getName();

                    $matches = array();
                    if (preg_match('/@column (\w+)/', $docComment, $matches) && count($matches) > 1) {
                        array_shift($matches);
                        $columnName = $matches[0];
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
     * @param string $class
     * @param string $propertyName
     * @return string|null
     */
    private function getAnnotatedPropertyType($class, $propertyName)
    {
        $type = null;
        $rf = new ReflectionClass($class);

        foreach ($rf->getProperties() as $property) {
            assert($property instanceof ReflectionProperty);

            $docComments = $property->getDocComment();

            $isDestinationProperty = false;
            $matches = array();
            if ($property->getName() == $propertyName) {
                $isDestinationProperty = true;
            }

            if (! $isDestinationProperty) {
                continue;
            }

            if ($docComments) {
                if (preg_match('/@var ([\w\\\\]+)/', $docComments, $matches) && count($matches) > 1) {
                    $type = $matches[1];
                    break;
                }
            }
        }

        return $type;
    }

    /**
     * Map default class object into specific by annotation
     *
     * @param \stdClass $from
     * @param string $toClass
     *
     * @return object
     *
     * @throws OrmException
     */
    private function mapAnnotated($from, $toClass)
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

                $type = $this->getAnnotatedPropertyType($toClass, $property->getName());

                if ($type && !$this->isPrimitive($type) && !class_exists($type)) {
                    $type = sprintf("\\%s\\%s", $rf->getNamespaceName(), $type);
                }

                if ($type && !$this->isPrimitive($type) && class_exists($type)) {
                    $rfPropertyType = new ReflectionClass($type);
                    if (strcmp($rfPropertyType->getParentClass()->name, 'Nkey\Caribu\Model\AbstractModel') == 0) {
                        $getById = new ReflectionMethod($type, "get");
                        $value = $getById->invoke(null, $value);
                    } else {
                        $value = $rfPropertyType->newInstance($value);
                    }
                }

                $method = sprintf("set%s", ucfirst($property->getName()));

                if ($resultClass->hasMethod($method)) {
                    $rfMethod = new ReflectionMethod($toClass, $method);
                    $rfMethod->invoke($result, $value);
                } else {
                    $resultClassProperties = $resultClass->getProperties();
                    foreach ($resultClassProperties as $resultClassProperty) {
                        assert($resultClassProperty instanceof ReflectionProperty);
                        $docComments = $resultClassProperty->getDocComment();
                        $matches = array();
                        if (preg_match('/@column (\w+)/', $docComments, $matches)) {
                            $destinationProperty = $matches[1];
                            if ($destinationProperty == $property->getName()) {
                                if (preg_match('/@var ([\w\\\\]+)/', $docComments, $matches)) {
                                    $type = $matches[1];
                                    if (!$this->isPrimitive($type)) {
                                        if (!class_exists($type)) {
                                            $type = sprintf("\\%s\\%s", $resultClass->getNamespaceName(), $type);
                                        }
                                        if (class_exists($type)) {
                                            if (!$value instanceof $type) {
                                                continue 2;
                                            }
                                        }
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
                    /*
                     * throw new OrmException("No method {method} provided by {class}", array(
                     * 'method' => $method,
                     * 'class' => $toClass
                     * ));
                     */
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
     * @param string $class
     * @param AbstractModel $object
     * @throws OrmException
     */
    private function persistAnnotated($class, $object)
    {
        try {
            $rf = new ReflectionClass($class);

            $persist = false;

            $docComments = $rf->getDocComment();
            if ($docComments && preg_match('/@cascade/', $docComments)) {
                $persist = true;
            }

            foreach ($rf->getProperties() as $property) {
                assert($property instanceof ReflectionProperty);
                $docComments = $property->getDocComment();
                $matches = array();
                if ($docComments && preg_match('/@var ([\w\\\\]+)/', $docComments, $matches)) {
                    $type = $matches[1];

                    if ($type && !$this->isPrimitive($type) && !class_exists($type)) {
                        $type = sprintf("\\%s\\%s", $rf->getNamespaceName(), $type);
                    }

                    if ($type && !$this->isPrimitive($type) && class_exists($type)) {
                        if (! $persist) {
                            $entityDocComments = $property->getDocComment();
                            if ($entityDocComments && preg_match('/@cascade/', $entityDocComments)) {
                                $persist = true;
                            }
                        }

                        $method = sprintf("get%s", ucfirst($property->getName()));
                        $rfMethod = new ReflectionMethod($this, $method);
                        $entity = $rfMethod->invoke($object);
                        if ($entity instanceof AbstractModel) {
                            if (! $persist) {
                                $pk = $this->getAnnotatedPrimaryKey($type);
                                if (null == $pk) {
                                    // TODO Retrieve using fallback
                                } elseif (is_array($pk)) {
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
     * @param string $class
     * @param object $object
     * @return array
     *
     * @throws OrmException
     */
    private function getAnnotatedColumnValuePairs($class, $object)
    {
        $pairs = array();
        try {
            $rf = new ReflectionClass($class);
            $properties = $rf->getProperties();

            foreach ($properties as $property) {
                assert($property instanceof ReflectionProperty);
                $column = $property->getName();

                $docComments = $property->getDocComment();
                $matches = array();
                if ($docComments && preg_match("/@column (\w+)/", $docComments, $matches) && count($matches) > 1) {
                    $column = $matches[1];
                }

                $method = sprintf("get%s", ucfirst($property->getName()));
                $rfMethod = new ReflectionMethod($class, $method);

                $pairs[$column] = $rfMethod->invoke($object);
            }
        } catch (ReflectionException $ex) {
            throw OrmException::fromPrevious($ex);
        }

        return $pairs;
    }

    /**
     * Retrieve the primary key value using annotation
     *
     * @param string $class
     * @return array
     *
     * @throws OrmException
     */
    private function getAnnotatedPrimaryKey($class, $object, $onlyValue = false)
    {
        $pk = null;
        try {
            $rf = new ReflectionClass($class);

            $properties = $rf->getProperties();

            foreach ($properties as $property) {
                assert($property instanceof ReflectionProperty);
                $docComment = $property->getDocComment();
                if ($docComment && preg_match('/@id/', $docComment)) {
                    $method = sprintf("get%s", ucfirst($property->getName()));
                    $rfMethod = new ReflectionMethod($class, $method);

                    $columnName = $property->getName();

                    $matches = array();
                    if (preg_match("/@column (\w+)/", $docComment, $matches) && count($matches) > 1) {
                        $columnName = $matches[1];
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
     * @param string $mappedBy
     * @return array
     */
    private function parseMappedBy($mappedBy)
    {
        $mappingOptions = array();
        foreach (explode(',', $mappedBy) as $mappingOption) {
            list ($option, $value) = preg_split('/=/', $mappingOption);
            $mappingOptions[$option] = $value;
        }

        return $mappingOptions;
    }

    /**
     * Get the annotated column name
     *
     * @param string $class
     * @param string $property
     * @return string
     */
    private function getAnnotatedColumnName($class, $property)
    {
        $columnName = "";

        $rfProperty = new ReflectionProperty($class, $property);
        $matches = array();
        if(preg_match("/@column (\w+)/", $rfProperty->getDocComment(), $matches)) {
            $columnName = $matches[1];
        }

        return $columnName;
    }

    /**
     * Get the annotated join query
     *
     * @param string $class
     * @param object $object
     * @param array $criteria
     *            (by reference)
     * @param array $columns
     *            (by reference)
     * @param array $wheres
     *            (by reference)
     *
     * @return string
     *
     * @throws OrmException
     */
    private function getAnnotatedQuery($class, $table, $object, &$criteria, &$columns, &$wheres)
    {
        $joinQuery = "";

        $rf = new ReflectionClass($class);

        // Example criterion: user.name => 'john'
        foreach (array_keys($criteria) as $criterion) {
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
            $matches = array();
            $propertyClass = "";
            // search the type of property value
            if (preg_match("/@var ([\w\\\\]+)/", $rfProperty->getDocComment(), $matches)) {
                if (!$this->isPrimitive($matches[1])) {
                    if (class_exists($matches[1])) {
                        $propertyClass = $matches[1];
                    }
                    else {
                        $classToFind = sprintf("\\%s\\%s", $rf->getNamespaceName(), $matches[1]);
                        if(class_exists($classToFind)) {
                            $propertyClass = $classToFind;
                            $matches[1] = $classToFind;
                        }
                    }
                }
            }
            $inverseTable = $propertyClass ? $this->getAnnotatedTableName($propertyClass, $criterion) : $criterion;

            // search the table mapping conditions
            if (preg_match("/@mappedBy\(([^\)].+)\)/", $rfProperty->getDocComment(), $matches)) {
                $mappedBy = $this->parseMappedBy($matches[1]);

                $pkCol = $this->getAnnotatedPrimaryKeyColumn($class);
                $inversePkCol = $this->getAnnotatedPrimaryKeyColumn($propertyClass);

                $joinQuery = sprintf(
                    "JOIN %s ON %s.%s = %s.%s ",
                    $mappedBy['table'],
                    $mappedBy['table'],
                    $mappedBy['inverseColumn'],
                    $table,
                    $pkCol
                );
                $joinQuery .= sprintf(
                    "JOIN %s ON %s.%s = %s.%s",
                    $inverseTable,
                    $inverseTable,
                    $inversePkCol,
                    $mappedBy['table'],
                    $mappedBy['column']
                );

                $columns[] = sprintf("%s.%s AS '%s.%s'", $inverseTable, $inversePkCol, $inverseTable, $inversePkCol);
            }
            elseif ($propertyClass != "") {
                $inversePkCol = $this->getAnnotatedPrimaryKeyColumn($propertyClass);
                $column = $this->getAnnotatedColumnName($class, $rfProperty->getName());
                $joinQuery = sprintf(
                    "JOIN %s AS %s ON %s.%s = %s.%s",
                    $inverseTable,
                    $criterion,
                    $criterion,
                    $inversePkCol,
                    $table,
                    $column
                );
                $columns[] = sprintf("%s.%s AS '%s.%s'", $criterion, $inversePkCol, $criterion, $inversePkCol);
            }
        }

        return $joinQuery;
    }

    /**
     * Checks whether a given string equals identifier of a primitive type
     *
     * @param string $type
     *
     * @return boolean true in case of string is identifier of primitive type, false otherwise
     */
    private function isPrimitive($type)
    {
        $isPrimitive = false;

        switch($type) {
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
