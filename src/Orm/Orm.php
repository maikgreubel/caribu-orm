<?php
namespace Nkey\Caribu\Orm;

use \Exception;

use \Generics\Util\Interpolator;

use \Nkey\Caribu\Model\AbstractModel;
use \Nkey\Caribu\Type\IType;

use \PDO;
use \PDOException;

use \ReflectionClass;
use \ReflectionMethod;
use \ReflectionException;
use \ReflectionProperty;

/**
 * The main object relational mapper class
 *
 * This class is part of Caribu package
 *
 * @author Maik Greubel <greubel@nkey.de>
 */
class Orm
{
    /**
     * Include the or mapping annotation functionality
     */
    use OrmAnnotation;

    /**
     * Include the transaction related functionality
     */
    use OrmTransaction;

    /**
     * Include exception handling related functionality
     */
    use OrmExceptionHandler;

    /**
     * Include the generics interpolation functionality
     */
    use Interpolator;

    /**
     * Singleton pattern
     *
     * @var Orm
     */
    private static $instance = null;

    /**
     * Singleton pattern
     *
     * @return Nkey\Caribu\Orm\Orm The current instance
     */
    public static function getInstance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Singleton pattern
     */
    private function __construct()
    {
        //TODO:Implement console logging of sql for debugging purposes
    }

    /**
     * Disable cloning
     */
    private function __clone()
    {
        //
    }

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

    /**
     * Retrieve particular dataset by given id
     *
     * @param mixed $id The primary key value of dataset row to retrieve
     *
     * @return \Nkey\Caribu\Model\AbstractModel
     *
     * @throws OrmException
     */
    public static function get($id)
    {
        $className = get_called_class();

        $pkColumn = self::getPrimaryKeyCol($className);

        $result = self::find(array(
            $pkColumn => $id
        ));

        if (! $result || is_array($result)) {
            throw new OrmException("More than one entity found (expected exactly one)");
        }

        return $result;
    }

    /**
     * Loops over all where conditions and create a string of it
     *
     * @param array $wheres
     * @return string The where conditions as string
     */
    private static function whereConditionsAsString(array $wheres)
    {
        if (count($wheres)) {
            $t = "";
            foreach ($wheres as $where) {
                $and = "";
                if ($t) {
                    $and = substr($where, 0, 3) == 'OR ' ? " " : " AND ";
                }
                $t .= $and . $where;
            }
            $wheres = sprintf("WHERE %s", $t);
        } else {
            $wheres = '';
        }

        return $wheres;
    }

    /**
     * Escape all parts of a criterion
     *
     * @param string $criterion The criterion pattern
     * @param string $escapeSign The escape sign
     */
    private static function escapeCriterion($criterion, $escapeSign)
    {
        $criterionEscaped = '';
        $criterionParts = explode('.', $criterion);

        foreach ($criterionParts as $part) {
            $criterionEscaped .= $criterionEscaped ? '.' : '';
            $criterionEscaped .= sprintf("%s%s%s", $escapeSign, $part, $escapeSign);
        }

        return $criterionEscaped;
    }

    /**
     * Parse criteria into where conditions
     *
     * @param array $criteria The criteria to parse
     * @return string The where conditions
     *
     * @throws OrmException
     */
    private static function parseCriteria(array &$criteria, $escapeSign)
    {
        $wheres = array();

        $criterias = array_keys($criteria);

        foreach ($criterias as $criterion) {
            $placeHolder = str_replace('.', '_', $criterion);
            $placeHolder = str_replace('OR ', 'OR_', $placeHolder);
            if (strtoupper(substr($criteria[$criterion], 0, 4)) == 'LIKE') {
                $wheres[] = sprintf("%s LIKE :%s", self::escapeCriterion($criterion, $escapeSign), $placeHolder);
            } elseif (strtoupper(substr($criteria[$criterion], 0, 7)) == 'BETWEEN') {
                $start = $end = null;
                sscanf(strtoupper($criteria[$criterion]), "BETWEEN %s AND %s", $start, $end);
                if (!$start || !$end) {
                    throw new OrmException("Invalid range for between");
                }
                $wheres[] = sprintf(
                    "%s BETWEEN %s AND %s",
                    self::escapeCriterion($criterion, $escapeSign),
                    $start,
                    $end
                );
                unset($criteria[$criterion]);
            } else {
                $wheres[] = sprintf("%s = :%s", self::escapeCriterion($criterion, $escapeSign), $placeHolder);
            }
        }

        return self::whereConditionsAsString($wheres);
    }

    /**
     * Prepare the limit and offset modifier
     *
     * @param int $limit
     * @param int $startFrom
     *
     * @return string The limit modifier or empty string
     */
    private static function parseLimits($limit = 0, $startFrom = 0)
    {
        $limits = "";
        if ($startFrom > 0) {
            $limits = sprintf("%d,", $startFrom);
        }
        if ($limit > 0) {
            $limits .= $limit;
        }

        if ($limits) {
            $limits = sprintf("LIMIT %s", $limits);
        }

        return $limits;
    }

    /**
     * Create a query for selection
     *
     * @param string $class The class for which the query will be created
     * @param array  $criteria Array of criterias in form of "property" => "value"
     * @param array  $columns The columns to retrieve
     * @param string $orderBy An order-by statement in form of "property ASC|DESC"
     * @param number $limit The maximum amount of results
     * @param number $startFrom The offset where to get results of
     *
     * @return string The query as sql statement
     *
     * @throws OrmException
     */
    private static function createQuery(
        $class,
        $tableName,
        array &$criteria,
        array $columns,
        $orderBy = '',
        $limit = 0,
        $startFrom = 0,
        $escapeSign = ""
    ) {
        $joins = self::getAnnotatedQuery($class, $tableName, $criteria, $columns, $escapeSign);

        $wheres = self::parseCriteria($criteria, $escapeSign);

        $limits = self::parseLimits($limit, $startFrom);

        if ($orderBy && ! stristr($orderBy, 'ORDER BY ')) {
            $orderBy = sprintf("ORDER BY %s%s%s", $escapeSign, $orderBy, $escapeSign);
        }

        $query = sprintf(
            "SELECT %s FROM %s%s%s %s %s %s %s",
            implode(',', $columns),
            $escapeSign,
            $tableName,
            $escapeSign,
            $joins,
            $wheres,
            $orderBy,
            $limits
        );

        return $query;
    }

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
    private static function map($from, $toClass)
    {
        $result = null;
        try {
            $result = self::mapAnnotated($from, $toClass);
            self::mapReferenced($from, $toClass, $result);
            if (self::isEager($toClass)) {
                self::injectMappedBy($toClass, $result);
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
            $rfToClass = new ReflectionClass($toClass);

            foreach (get_object_vars($from) as $property => $value) {
                if (strpos($property, '.')) {
                    list($toProperty, $column) = explode('.', $property);

                    if ($rfToClass->hasProperty($toProperty)) {
                        $referencedClass = self::getAnnotatedPropertyType($toClass, $toProperty);

                        if (!class_exists($referencedClass)) {
                            $referencedClass = sprintf("\\%s\\%s", $rfToClass->getNamespaceName(), $referencedClass);
                        }

                        $rfReferenced = new ReflectionClass($referencedClass);

                        $findMethod = $rfReferenced->getMethod("find");
                        $referencedObject = $findMethod->invoke(null, array($column => $value));

                        $propertySetter = $rfToClass->getMethod(sprintf("set%s", ucfirst($toProperty)));

                        $propertySetter->invoke($result, $referencedObject);
                    }
                }
            }
        } catch (ReflectionException $ex) {
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
    private static function injectMappedBy($toClass, &$object)
    {
        $instance = self::getInstance();
        assert($instance instanceof Orm);

        try {
            $rfToClass = new ReflectionClass($toClass);

            foreach ($rfToClass->getProperties() as $property) {
                assert($property instanceof ReflectionProperty);

                if (null !== ($parameters = self::getAnnotatedMappedByParameters($property->getDocComment()))) {
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

                    $getMethod = new ReflectionMethod($toClass, sprintf("get%s", ucfirst($property->getName())));
                    if ($getMethod->invoke($object)) {
                        continue;
                    }

                    $ownPrimaryKey = self::getPrimaryKey($toClass, $object, true);

                    $otherTable = self::getTableName($type);
                    $otherPrimaryKeyName = self::getPrimaryKeyCol($type);
                    $ownPrimaryKeyName =  self::getPrimaryKeyCol($toClass);

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
                        $statement = $instance->startTX()->prepare($query);
                        $statement->bindValue(sprintf(":%s", $ownPrimaryKeyName), $ownPrimaryKey);

                        $statement->execute();

                        $result = $statement->fetch(PDO::FETCH_OBJ);

                        if (false == $result) {
                            throw new OrmException(
                                "No foreign entity found for {entity} using primary key {pk}",
                                array('entity' => $toClass, 'pk' => $$ownPrimaryKey)
                            );
                        }

                        $instance->commitTX();

                        $setMethod = new ReflectionMethod($toClass, sprintf("set%s", ucfirst($property->getName())));

                        $setMethod->invoke($object, self::map($result, $type));
                    } catch (PDOException $ex) {
                        throw self::handleException($instance, $statement, $ex, "Mapping failed", - 1010);
                    }
                }
            }
        } catch (ReflectionException $ex) {
            throw OrmException::fromPrevious($ex);
        }
    }

    /**
     * Find data sets by given criteria
     *
     * @param array $criteria Array of criterias in form of "property" => "value"
     * @param string $orderBy An order-by statement in form of "property ASC|DESC"
     * @param int $limit The maximum amount of results
     * @param int $startOffset The offset where to get results of
     * @param boolean $asList Fetch results as list, also if number of results is one
     *
     * @return Nkey\Caribu\Orm\AbstractModel|array|null Either an array of entities, a single entity (if only one was found) or null
     *
     * @throws OrmException
     */
    public static function find(array $criteria, $orderBy = "", $limit = 0, $startOffset = 0, $asList = false)
    {
        $results = null;

        $instance = self::getInstance();
        assert($instance instanceof Orm);

        $class = get_called_class();

        $table = self::getTableName($class);

        $escapeSign = $instance->getDbType()->getEscapeSign();

        $query = self::createQuery(
            $class,
            $table,
            $criteria,
            array(sprintf("%s%s%s.*", $escapeSign, $table, $escapeSign)),
            $orderBy,
            $limit,
            $startOffset,
            $escapeSign
        );
        $statement = null;

        try {
            $statement = $instance->startTX()->prepare($query);

            foreach ($criteria as $criterion => $value) {
                $placeHolder = str_replace('.', '_', $criterion);
                $placeHolder = str_replace('OR ', 'OR_', $placeHolder);
                $value = str_ireplace('LIKE ', '', $value);
                $statement->bindValue(":" . $placeHolder, $value);
            }

            $statement->execute();

            $unmapped = array();
            while ($result = $statement->fetch(PDO::FETCH_OBJ)) {
                $unmapped[] = $result;
            }

            $statement->closeCursor();

            foreach ($unmapped as $result) {
                $results[] = self::map($result, $class);
            }

            if (!$asList && count($results) == 1) {
                $results = $results[0];
            }

            $instance->commitTX();
        } catch (Exception $ex) {
            throw self::handleException(
                $instance,
                $statement,
                $ex,
                "Finding data set failed",
                -100
            );
        }

        return $results;
    }

    /**
     * Find data sets by given criteria
     *
     * @param array $criteria Array of criterias in form of "property" => "value"
     * @param string $orderBy  An order-by statement in form of "property ASC|DESC"
     * @param int $limit The maximum amount of results
     * @param int $startOffset The offset where to get results of
     *
     * @return mixed  Either an array of object, a single object (if only one was found) or null
     *
     * @throws OrmException
     */
    public static function findAll(array $criteria = array(), $orderBy = "", $limit = 0, $startOffset = 0)
    {
        return self::find($criteria, $orderBy, $limit, $startOffset, true);
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
                $rfMethod = new ReflectionMethod($class, $method);
                $pk = $rfMethod->invoke($object);
                if (!$onlyValue) {
                    $pk = array(
                        $pkCol => $pk
                    );
                }

            } catch (ReflectionException $ex) {
                throw OrmException::fromPrevious($ex);
            }
        }

        return $pk;
    }

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
            $rfMethod = new ReflectionMethod($class, $method);
            $rfMethod->invoke($object, $primaryKey);
        } catch (ReflectionException $ex) {
            throw OrmException::fromPrevious($ex);
        }
    }

    /**
     * Retrieve the persistence parameters via reflection
     *
     * @param array $pairs The pairs of column names => values
     *
     * @return string The prepared statement parameters for persistence
     *
     * @throws OrmException
     */
    private static function persistenceQueryParams($pairs, $primaryKeyCol, $insert = true, $escapeSign = "")
    {
        $query = "";

        $columns = array_keys($pairs);

        if ($insert) {
            $cols = "";
            $vals = "";
            foreach ($columns as $column) {
                $cols .= ($cols ? ',' : '');
                $cols .= sprintf("%s%s%s", $escapeSign, $column, $escapeSign);
                $vals .= ($vals ? ',' : '');
                $vals .= sprintf(':%s', $column);
            }
            $query = sprintf("(%s) VALUES (%s)", $cols, $vals);
        } else {
            foreach ($columns as $column) {
                if ($column == $primaryKeyCol) {
                    continue;
                }
                $query .= ($query ? ", " : "SET ");
                $query .= sprintf("%s%s%s = :%s", $escapeSign, $column, $escapeSign, $column);
            }
        }

        return $query;
    }

    /**
     * Persist the mapped-by entities
     *
     * @param string $class The name of class of which the data has to be persisted
     * @param AbstractModel $object The entity which contain mapped-by entries to persist
     *
     * @throws OrmException
     * @throws PDOException
     */
    private static function persistMappedBy($class, $object)
    {
        $instance = self::getInstance();
        assert($instance instanceof Orm);

        $escapeSign = $instance->getDbType()->getEscapeSign();

        try {
            $rf = new ReflectionClass($class);

            foreach ($rf->getProperties() as $property) {
                assert($property instanceof ReflectionProperty);

                if (null !== ($parameters = self::getAnnotatedMappedByParameters($property->getDocComment()))) {
                    $mappedBy = self::parseMappedBy($parameters);

                    $method = sprintf("get%s", ucfirst($property->getName()));
                    $rfMethod = new ReflectionMethod($class, $method);
                    assert($rfMethod instanceof ReflectionMethod);
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
                        } catch (PDOException $ex) {
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
        } catch (ReflectionException $ex) {
            throw OrmException::fromPrevious($ex);
        }
    }

    /**
     * Create a insert or update statement
     *
     * @param string    $class              The class of entity
     * @param array     $pairs              The pairs of columns and its corresponding values
     * @param string    $primaryKeyCol      The name of column which represents the primary key
     * @param mixed     $primaryKeyValue    The primary key value
     *
     * @return string
     */
    private static function createUpdateStatement($class, $pairs, $primaryKeyCol, $primaryKeyValue, $escapeSign)
    {
        $tableName = self::getTableName($class);

        $query = sprintf("INSERT INTO %s%s%s ", $escapeSign, $tableName, $escapeSign);
        if ($primaryKeyValue) {
            $query = sprintf("UPDATE %s%s%s ", $escapeSign, $tableName, $escapeSign);
        }

        $query .= self::persistenceQueryParams($pairs, $primaryKeyCol, is_null($primaryKeyValue), $escapeSign);

        if ($primaryKeyValue) {
            $query .= sprintf(" WHERE %s%s%s = :%s", $escapeSign, $primaryKeyCol, $escapeSign, $primaryKeyCol);
        }

        return $query;
    }

    /**
     * Persist the object into database
     *
     * @throws OrmException
     */
    public function persist()
    {
        $instance = self::getInstance();
        assert($instance instanceof Orm);

        $escapeSign = $instance->getDbType()->getEscapeSign();

        $entity = $this;
        assert($entity instanceof \Nkey\Caribu\Model\AbstractModel);
        $class = get_class($entity);

        $tableName = self::getTableName($class);

        self::persistAnnotated($class, $entity);

        $pk = self::getPrimaryKey($class, $entity);
        if (is_null($pk)) {
            throw new OrmException("No primary key column found!");
        }
        $primaryKeyCol = array_keys($pk)[0];
        $primaryKeyValue = array_values($pk)[0];

        $pairs = self::getAnnotatedColumnValuePairs($class, $entity);

        $query = self::createUpdateStatement($class, $pairs, $primaryKeyCol, $primaryKeyValue, $escapeSign);

        $connection = $instance->startTX();
        $statement = null;


        try {
            $statement = $connection->prepare($query);

            foreach ($pairs as $column => $value) {
                if ($value instanceof \Nkey\Caribu\Model\AbstractModel) {
                    $value = self::getPrimaryKey(get_class($value), $value, true);
                }
                $statement->bindValue(":{$column}", self::convertToDatabaseType(self::getColumnType(
                    $instance, $tableName, $column), $value
                ));
            }
            if ($primaryKeyValue) {
                $statement->bindValue(":{$primaryKeyCol}", $primaryKeyValue);
            }

            $instance->getDbType()->lock($tableName, IType::LOCK_TYPE_WRITE, $instance);
            $statement->execute();
            if (!$primaryKeyValue) {
                $pk = $connection->lastInsertId($instance->getDbType()->getSequenceNameForColumn(
                    $tableName, $primaryKeyCol, $instance
                ));
            }
            $instance->getDbType()->unlock($tableName, $instance);

            unset($statement);

            if (!$primaryKeyValue) {
                $this->setPrimaryKey($class, $entity, $pk);
            }

            $this->persistMappedBy($class, $entity);

            $instance->commitTX();
        } catch (PDOException $ex) {
            $instance->getDbType()->unlock($tableName, $instance);
            throw self::handleException($instance, $statement, $ex, "Persisting data set failed", - 1000);
        }
    }

    /**
     * Removes the current persisted entity
     *
     * @throws OrmException
     */
    public function delete()
    {
        $instance = self::getInstance();
        assert($instance instanceof Orm);

        $class = get_class($this);

        $tableName = $this->getTableName($class);

        $escapeSign = $this->getDbType()->getEscapeSign();

        $pk = self::getPrimaryKey($class, $this);
        $primaryKeyCol = array_keys($pk)[0];
        $primaryKeyValue = array_values($pk)[0];

        if (is_null($primaryKeyValue)) {
            throw new OrmException("Entity is not persisted or detached. Can not delete.");
        }

        $query = sprintf(
            "DELETE FROM %s%s%s WHERE %s%s%s = :%s",
            $escapeSign,
            $tableName,
            $escapeSign,
            $escapeSign,
            $primaryKeyCol,
            $escapeSign,
            $primaryKeyCol
        );

        $statement = null;

        try {
            $statement = $instance->startTX()->prepare($query);
            $statement->bindValue(":{$primaryKeyCol}", $primaryKeyValue);

            $instance->getDbType()->lock($tableName, IType::LOCK_TYPE_WRITE, $instance);
            $statement->execute();
            $instance->getDbType()->unlock($tableName, $instance);
            unset($statement);
            $instance->commitTX();
        } catch (PDOException $ex) {
            $instance->getDbType()->unlock($tableName, $instance);
            throw self::handleException($instance, $statement, $ex, "Persisting data set failed", - 1000);
        }
    }

    /**
     * Destroy the orm instance
     */
    public static function passivate()
    {
        if (self::$instance && self::$instance->connection) {
            unset(self::$instance->connection);
        }

        if (self::$instance) {
            self::$instance = null;
        }
    }
}
