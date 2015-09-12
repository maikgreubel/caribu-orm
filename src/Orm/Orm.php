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
     * Include the transaction related functionality
     */
    use OrmTransaction;

    /**
     * Include mapping related functionality
     */
    use OrmMapping;

    /**
     * Include exception handling related functionality
     */
    use OrmExceptionHandler;

    /**
     * Include statement related functionality
     */
    use OrmStatement;

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

        if (!$result || is_array($result)) {
            throw new OrmException("More than one entity found (expected exactly one)");
        }

        return $result;
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
     * @return Nkey\Caribu\Orm\AbstractModel|array|null
     *          Either an array of entities, a single entity (if only one was found) or null
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
                $results[] = self::map($result, $class, $instance);
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
                $statement->bindValue(
                    ":{$column}",
                    self::convertToDatabaseType(self::getColumnType($instance, $tableName, $column), $value)
                );
            }
            if ($primaryKeyValue) {
                $statement->bindValue(":{$primaryKeyCol}", $primaryKeyValue);
            }

            $instance->getDbType()->lock($tableName, IType::LOCK_TYPE_WRITE, $instance);
            $statement->execute();
            if (!$primaryKeyValue) {
                $pk = $connection->lastInsertId($instance->getDbType()->getSequenceNameForColumn(
                    $tableName,
                    $primaryKeyCol,
                    $instance
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
