<?php
namespace Nkey\Caribu\Orm;

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
     * Get the property of class based on annotation or as fallback
     *
     * @param string $class
     * @param string $propertyName
     * @return string
     *
     * @throws OrmException
     */
    private function getAnnotatedProperty($class, $propertyName)
    {
        $rf = new ReflectionClass($class);

        $properties = $rf->getProperties();

        foreach ($properties as $property) {
            assert($property instanceof ReflectionProperty);

            if ($property->getName() == $propertyName) {
                return $propertyName;
            }

            $docComment = $property->getDocComment();
            $matches = array();
            if ($docComment && preg_match('/@column (\w+)/', $docComment, $matches) && count($matches) > 1) {
                array_shift($matches);
                if ($matches[0] == $propertyName) {
                    return $property->getName();
                }
            }
        }

        throw new OrmException("No such property {property} in class {class}", array(
            'property' => $propertyName,
            'class' => $class
        ));
    }

    /**
     * Get the property type via annotation
     * @param string $class
     * @param string $propertyName
     * @return string|null
     */
    private function getAnnotatedPropertyType($class, $propertyName)
    {
        $type = null;
        $rf = new ReflectionClass($class);

        foreach($rf->getProperties() as $property) {
            assert($property instanceof ReflectionProperty);

            $docComments = $property->getDocComment();

            $isDestinationProperty = false;
            $matches = array();
            if($property->getName() == $propertyName) {
                $isDestinationProperty = true;
            }

            if(!$isDestinationProperty) {
                continue;
            }

            if ($docComments) {
                if(preg_match('/@var ([\w\\\\]+)/', $docComments, $matches) && count($matches) > 1) {
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

                $value = $property->getValue($from);

                $type = $this->getAnnotatedPropertyType($toClass, $property->getName());

                if (class_exists($type)) {
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
                        if ($docComments && preg_match('/@column (\w+)/', $docComments, $matches)) {
                            array_shift($matches);
                            $destinationProperty = $matches[0];
                            if ($destinationProperty == $property->getName()) {
                                $method = sprintf("set%s", ucfirst($resultClassProperty->getName()));
                                if ($resultClass->hasMethod($method)) {
                                    $rfMethod = new ReflectionMethod($toClass, $method);
                                    $rfMethod->invoke($result, $value);
                                    continue 2;
                                }
                            }
                        }
                    }

                    throw new OrmException("No method {method} provided by {class}", array(
                        'method' => $method,
                        'class' => $toClass
                    ));
                }
            }

            return $result;
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

                $method = sprintf("get%s", ucfirst($column));
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
    private function getAnnotatedPrimaryKey($class)
    {
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

                    return array(
                        $columnName => $rfMethod->invoke($this)
                    );
                }
            }
        } catch (ReflectionException $ex) {
            throw OrmException::fromPrevious($ex);
        }

        return null;
    }
}