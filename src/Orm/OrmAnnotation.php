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
     * @param string $class
     *            The name of class
     * @param string $fallback
     *            As fallback if nothing was found
     *            
     * @return string The name of table
     *        
     * @throws OrmException
     */
    private static function getAnnotatedTableName(string $class, string $fallback): string
    {
        try {
            $rfClass = new \ReflectionClass($class);
            
            $docComments = $rfClass->getDocComment();
            
            $matches = array();
            if (preg_match('/@table (\w+)/', $docComments, $matches)) {
                $fallback = $matches[1];
            }
            
            return $fallback;
        } catch (\ReflectionException $exception) {
            throw OrmException::fromPrevious($exception);
        }
    }

    /**
     * Get the annotated primary key property name
     *
     * The property is annotated with the @id annotation
     *
     * @param string $class
     *            The name of class to retrieve the primary key property
     *            
     * @return string The name of property which represents the primary key
     *        
     * @throws OrmException
     */
    private static function getAnnotatedPrimaryKeyProperty(string $class): string
    {
        try {
            $propertyName = "";
            
            foreach (self::getClassProperties($class) as $property) {
                if (self::isIdAnnotated($property->getDocComment())) {
                    $propertyName = $property->getName();
                    break;
                }
            }
            
            return $propertyName;
        } catch (\ReflectionException $exception) {
            throw OrmException::fromPrevious($exception);
        }
    }

    /**
     * Get the annotated primary key
     *
     * The property is annotated with the @id annotation
     * The propery may have a @column annotation to modify the database column name
     *
     * @param string $class
     *            The name of class to retrieve the primary key column of
     *            
     * @return string|null The name of primary key column
     *        
     * @throws OrmException
     */
    private static function getAnnotatedPrimaryKeyColumn(string $class): string
    {
        try {
            $columnName = "";
            
            foreach (self::getClassProperties($class) as $property) {
                $docComment = $property->getDocComment();
                if (self::isIdAnnotated($docComment) && "" === ($columnName = self::getAnnotatedColumn($docComment))) {
                    $columnName = $property->getName();
                    break;
                }
            }
            
            return $columnName;
        } catch (\ReflectionException $exception) {
            throw OrmException::fromPrevious($exception);
        }
    }

    /**
     * Get the property type via annotation
     *
     * @param string $class
     *            The name of class to retrieve a particular property type
     * @param string $propertyName
     *            The name of property to retrieve the type of
     * @param string $namespace
     *            The namespace
     *            
     * @return string|null The property type either as primitive type or full qualified class
     */
    private static function getAnnotatedPropertyType(string $class, string $propertyName, string $namespace): string
    {
        $type = "";
        
        $rfClass = new \ReflectionClass(self::fullQualifiedName($namespace, $class));
        
        if ($rfClass->hasProperty($propertyName)) {
            $property = $rfClass->getProperty($propertyName);
            $type = self::getAnnotatedType($property->getDocComment(), $rfClass->getNamespaceName());
        }
        
        return $type;
    }

    /**
     * Get the value from property
     *
     * @param object $from
     *            The source object
     * @param string $toClass
     *            The type of destination class
     * @param \ReflectionProperty $property
     *            The property to get value of
     * @param string $namespace
     *            The namespace of destination class
     *            
     * @return array The type and value from property
     */
    private static function getAnnotatedPropertyValue(\stdClass $from, string $toClass, \ReflectionProperty $property, string $namespace): array
    
    {
        $value = $property->getValue($from);
        
        $type = self::getAnnotatedPropertyType($toClass, $property->getName(), $namespace);
        
        if ("" === $type || self::isPrimitive($type) || ! class_exists($type)) {
            return array(
                $type,
                $value
            );
        }
        
        $rfPropertyType = new \ReflectionClass($type);
        
        if ($rfPropertyType->getParentClass() && strcmp($rfPropertyType->getParentClass()->name, 'Nkey\Caribu\Model\AbstractModel') == 0) {
            $getById = new \ReflectionMethod($type, "get");
            $value = $getById->invoke(null, $value);
            
            return array(
                $type,
                $value
            );
        }
        
        $value = $rfPropertyType->isInternal() ? self::convertType($type, $value) : $rfPropertyType->newInstance($value);
        
        return array(
            $type,
            $value
        );
    }

    /**
     * Retrieve list of columns and its corresponding pairs
     *
     * @param string $class
     *            The name of class to retrieve all column-value pairs of
     * @param \Nkey\Caribu\Model\AbstractModel $object
     *            The entity to get the column-value pairs of
     *            
     * @return array List of column => value pairs
     *        
     * @throws OrmException
     */
    private static function getAnnotatedColumnValuePairs(string $class, \Nkey\Caribu\Model\AbstractModel $object): array
    {
        $pairs = array();
        try {
            $rfClass = new \ReflectionClass($class);
            
            foreach ($rfClass->getProperties() as $property) {
                $docComments = $property->getDocComment();
                
                // mapped by entries have no corresponding table column, so we skip it here
                if (preg_match('/@mappedBy/i', $docComments)) {
                    continue;
                }
                if ("" === ($column = self::getAnnotatedColumn($docComments))) {
                    $column = $property->getName();
                }
                
                $rfMethod = new \ReflectionMethod($class, sprintf("get%s", ucfirst($property->getName())));
                
                $value = $rfMethod->invoke($object);
                if (null != $value) {
                    $pairs[$column] = $value;
                }
            }
        } catch (\ReflectionException $exception) {
            throw OrmException::fromPrevious($exception);
        }
        
        return $pairs;
    }

    /**
     * Retrieve the primary key name and value using annotation
     *
     * @param string $class
     *            The name of class to retrieve the primary key name and value
     * @param \Nkey\Caribu\Model\AbstractModel $object
     *            The entity to retrieve the pimary key value
     * @param bool $onlyValue
     *            Whether to retrieve only the value instead of name and value
     *            
     * @return array The "name" => "value" of primary key or only the value (depending on $onlyValue)
     *        
     * @throws OrmException
     */
    private static function getAnnotatedPrimaryKey(string $class, \Nkey\Caribu\Model\AbstractModel $object, bool $onlyValue)
    {
        try {
            $rfClass = new \ReflectionClass($class);
            
            foreach ($rfClass->getProperties() as $property) {
                $docComment = $property->getDocComment();
                
                if (! self::isIdAnnotated($docComment)) {
                    continue;
                }
                
                $rfMethod = new \ReflectionMethod($class, sprintf("get%s", ucfirst($property->getName())));
                
                if ("" === ($columnName = self::getAnnotatedColumn($docComment))) {
                    $columnName = $property->getName();
                }
                
                $primaryKey = $rfMethod->invoke($object);
                
                if (! $onlyValue) {
                    $primaryKey = array(
                        $columnName => $primaryKey
                    );
                }
                return $primaryKey;
            }
            return null;
        } catch (\ReflectionException $exception) {
            throw OrmException::fromPrevious($exception);
        }
    }

    /**
     * Get the annotated type
     *
     * @param string $comment
     *            The document comment string which may contain the @var annotation
     * @param string $namespace
     *            Optional namespace where class is part of
     *            
     * @return string The parsed type
     *        
     * @throws OrmException
     */
    private static function getAnnotatedType(string $comment, string $namespace = null): string
    {
        $matches = array();
        if (! preg_match('/@var ([\w\\\\]+)/', $comment, $matches)) {
            return "";
        }
        
        $type = $matches[1];
        
        if (self::isPrimitive($type)) {
            return $type;
        }
        
        $type = self::fullQualifiedName($namespace, $type);
        
        if (! class_exists($type)) {
            throw new OrmException("Annotated type {type} could not be found nor loaded", array(
                'type' => $matches[1]
            ));
        }
        
        return $type;
    }

    /**
     * Get the annotated column name
     *
     * @param string $class
     *            The name of class to retrieve te annotated column name
     * @param string $property
     *            The property which is annotated by column name
     *            
     * @return string The column name
     */
    private static function getAnnotatedColumnFromProperty(string $class, string $property): string
    {
        $rfProperty = new \ReflectionProperty($class, $property);
        return self::getAnnotatedColumn($rfProperty->getDocComment());
    }

    /**
     * Get the annotated column name from document comment string
     *
     * @param string $comment
     *            The document comment which may contain the @column annotation
     *            
     * @return string|null The parsed column name
     */
    private static function getAnnotatedColumn(string $comment): string
    {
        $columnName = "";
        
        $matches = array();
        if (preg_match("/@column (\w+)/", $comment, $matches)) {
            $columnName = $matches[1];
        }
        return $columnName;
    }

    /**
     * Check whether property is annotated using @id
     *
     * @param string $comment
     *            The document comment which may contain the @id annotation
     *            
     * @return bool true in case of it is annotated, false otherwise
     */
    private static function isIdAnnotated(string $comment): bool
    {
        return preg_match('/@id/', $comment) > 0 ? true : false;
    }

    /**
     * Check whether property is annotated using @cascade
     *
     * @param string $comment
     *            The document comment which may contain the @cascade annotation
     *            
     * @return bool true in case of it is annotated, false otherwise
     */
    private static function isCascadeAnnotated(string $comment): bool
    {
        return preg_match('/@cascade/', $comment) > 0 ? true : false;
    }

    /**
     * Get the mappedBy parameters from documentation comment
     *
     * @param string $comment
     *            The documentation comment to parse
     *            
     * @return string The parsed parameters or null
     */
    private static function getAnnotatedMappedByParameters(string $comment): string
    {
        $parameters = "";
        
        $matches = array();
        if (preg_match('/@mappedBy\(([^\)].+)\)/', $comment, $matches)) {
            $parameters = $matches[1];
        }
        return $parameters;
    }

    /**
     * Checks whether an entity has eager fetch type
     *
     * @param string $class
     *            Name of class of entity
     *            
     * @return bool true if fetch type is eager, false otherwise
     */
    private static function isEager(string $class): bool
    {
        $rf = new \ReflectionClass($class);
        return preg_match('/@eager/', $rf->getDocComment()) > 0 ? true : false;
    }
}
