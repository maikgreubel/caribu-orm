<?php
declare(strict_types = 1);
namespace Nkey\Caribu\Orm;

use \Nkey\Caribu\Model\AbstractModel;
use \Nkey\Caribu\Type\IType;

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
     * Include persisting related functionality
     */
    use OrmPersister;
    
    /**
     * Include the generics interpolation functionality
     */
    use \Generics\Util\Interpolator;

    /**
     * Singleton pattern
     *
     * @var Orm
     */
    private static $instance = null;

    /**
     * Singleton pattern
     *
     * @return Orm The current instance
     */
    public static function getInstance(): Orm
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
        // TODO:Implement console logging of sql for debugging purposes
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
     * @param mixed $id
     *            The primary key value of dataset row to retrieve
     *            
     * @return \Nkey\Caribu\Model\AbstractModel
     *
     * @throws OrmException
     */
    public static function get($id): \Nkey\Caribu\Model\AbstractModel
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
     * Find data sets by given criteria
     *
     * @param array $criteria
     *            Array of criterias in form of "property" => "value"
     * @param string $orderBy
     *            An order-by statement in form of "property ASC|DESC"
     * @param int $limit
     *            The maximum amount of results
     * @param int $startOffset
     *            The offset where to get results of
     * @param bool $asList
     *            Fetch results as list, also if number of results is one
     *            
     * @return Nkey\Caribu\Orm\AbstractModel|array|null Either an array of entities, a single entity (if only one was found) or null
     *        
     * @throws OrmException
     */
    public static function find(array $criteria, string $orderBy = "", int $limit = 0, int $startOffset = 0, bool $asList = false)
    {
        $results = null;
        
        $instance = self::getInstance();
        
        $class = get_called_class();
        
        $table = self::getTableName($class);
        
        $escapeSign = $instance->getDbType()->getEscapeSign();
        
        $query = self::createQuery($class, $table, $criteria, array(
            sprintf("%s%s%s.*", $escapeSign, $table, $escapeSign)
        ), $orderBy, $limit, $startOffset, $escapeSign);
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
            while ($result = $statement->fetch(\PDO::FETCH_OBJ)) {
                $unmapped[] = $result;
            }
            
            $statement->closeCursor();
            
            foreach ($unmapped as $result) {
                $results[] = self::map($result, $class, $instance);
            }
            
            if (! $asList && count($results) == 1) {
                $results = $results[0];
            }
            
            $instance->commitTX();
        } catch (\Exception $exception) {
            throw self::handleException($instance, $statement, $exception, "Finding data set failed", - 100);
        }
        
        return $results;
    }

    /**
     * Find data sets by given criteria
     *
     * @param array $criteria
     *            Array of criterias in form of "property" => "value"
     * @param string $orderBy
     *            An order-by statement in form of "property ASC|DESC"
     * @param int $limit
     *            The maximum amount of results
     * @param int $startOffset
     *            The offset where to get results of
     *            
     * @return mixed Either an array of object, a single object (if only one was found) or null
     *        
     * @throws OrmException
     */
    public static function findAll(array $criteria = array(), string $orderBy = "", int $limit = 0, int $startOffset = 0)
    {
        return self::find($criteria, $orderBy, $limit, $startOffset, true);
    }

    /**
     * Persist the object into database
     *
     * @throws OrmException
     */
    public function persist()
    {
        $instance = self::getInstance();
        
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
                $statement->bindValue(":{$column}", self::convertToDatabaseType(self::getColumnType($instance, $tableName, $column), $value));
            }
            if ($primaryKeyValue) {
                $statement->bindValue(":{$primaryKeyCol}", $primaryKeyValue);
            }
            
            $instance->getDbType()->lock($tableName, IType::LOCK_TYPE_WRITE, $instance);
            $statement->execute();
            if (! $primaryKeyValue) {
                $pk = $connection->lastInsertId($instance->getDbType()
                    ->getSequenceNameForColumn($tableName, $primaryKeyCol, $instance));
            }
            $instance->getDbType()->unlock($tableName, $instance);
            
            unset($statement);
            
            if (! $primaryKeyValue) {
                $this->setPrimaryKey($class, $entity, $pk);
            }
            
            $this->persistMappedBy($class, $entity);
            
            $instance->commitTX();
        } catch (\PDOException $exception) {
            $instance->getDbType()->unlock($tableName, $instance);
            throw self::handleException($instance, $statement, $exception, "Persisting data set failed", - 1000);
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
        
        assert($this instanceof \Nkey\Caribu\Model\AbstractModel);
        
        $class = get_class($this);
        
        $tableName = $this->getTableName($class);
        
        $escapeSign = $this->getDbType()->getEscapeSign();
        
        $pk = self::getPrimaryKey($class, $this, false);
        $primaryKeyCol = array_keys($pk)[0];
        $primaryKeyValue = array_values($pk)[0];
        
        if (is_null($primaryKeyValue)) {
            throw new OrmException("Entity is not persisted or detached. Can not delete.");
        }
        
        $query = sprintf("DELETE FROM %s%s%s WHERE %s%s%s = :%s", $escapeSign, $tableName, $escapeSign, $escapeSign, $primaryKeyCol, $escapeSign, $primaryKeyCol);
        
        $statement = null;
        
        try {
            $statement = $instance->startTX()->prepare($query);
            $statement->bindValue(":{$primaryKeyCol}", $primaryKeyValue);
            
            $instance->getDbType()->lock($tableName, IType::LOCK_TYPE_WRITE, $instance);
            $statement->execute();
            $instance->getDbType()->unlock($tableName, $instance);
            unset($statement);
            $instance->commitTX();
        } catch (\PDOException $exception) {
            $instance->getDbType()->unlock($tableName, $instance);
            throw self::handleException($instance, $statement, $exception, "Persisting data set failed", - 1000);
        }
    }

    /**
     * Destroy the orm instance
     */
    public static function passivate()
    {
        if (self::$instance) {
            self::$instance->passivateConnection();
            self::$instance = null;
        }
    }
}
