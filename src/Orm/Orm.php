<?php
namespace Nkey\Caribu\Orm;

use \Exception;
use \Generics\Logger\LoggerTrait;
use \Generics\Util\Interpolator;
use \Nkey\Caribu\Model\AbstractModel;
use \Nkey\Caribu\Type\AbstractType;
use \Nkey\Caribu\Type\TypeFactory;
use \PDO;
use \PDOException;
use \PDOStatement;
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
     * Include the generics interpolation functionality
     */
    use Interpolator;

    /**
     * Include the or mapping annotation functionality
     */
    use OrmAnnotation;

    /**
     * Include logging facility
     */
    use LoggerTrait;

    /**
     * Singleton pattern
     *
     * @var Orm
     */
    private static $instance = null;

    /**
     * The concrete database type
     *
     * @var string
     */
    private $type = null;

    /**
     * The database schema
     *
     * @var string
     */
    private $schema = null;

    /**
     * Database connection user
     *
     * @var string
     */
    private $user = null;

    /**
     * Database connection password
     *
     * @var string
     */
    private $password = null;

    /**
     * Database connection host
     *
     * @var string
     */
    private $host = null;

    /**
     * Database connection port
     *
     * @var int
     */
    private $port = null;

    /**
     * Embedded database file
     *
     * @var string
     */
    private $file = null;

    /**
     * Settings to use for connection
     *
     * @var array
     */
    private $settings = null;

    /**
     * The database connection
     *
     * @var PDO
     */
    private $connection = null;

    /**
     * Database type
     *
     * @var AbstractType
     */
    private $dbType = null;

    /**
     * The stack of open transactions
     *
     * @var int
     */
    private $transactionStack = 0;

    /**
     * Singleton pattern
     *
     * @return \Nkey\Caribu\Orm\Orm
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
        //$this->setLogger(new ConsoleLogger(new ConsoleOutput()));
    }

    /**
     * Disable cloning
     */
    private function __clone()
    {
        //
    }

    /**
     * Configure the Orm
     *
     * @param array $options Various options to use for configuration. See documentation for details.
     */
    public static function configure($options = array())
    {
        self::parseOptions($options);
    }

    /**
     * Parse the options
     *
     * @param array $options The options to parse
     */
    private static function parseOptions($options)
    {
        foreach ($options as $option => $value) {
            switch ($option) {
                case 'type':
                    self::getInstance()->type = $value;
                    break;

                case 'schema':
                    self::getInstance()->schema = $value;
                    break;

                case 'user':
                    self::getInstance()->user = $value;
                    break;

                case 'password':
                    self::getInstance()->password = $value;
                    break;

                case 'host':
                    self::getInstance()->host = $value;
                    break;

                case 'port':
                    self::getInstance()->port = $value;
                    break;

                case 'file':
                    self::getInstance()->file = $value;
                    break;

                default:
                    self::getInstance()->settings[$option] = $value;
            }
        }
    }

    /**
     * Interpolate a given string
     *
     * @param string $string The string to interpolate
     * @param array $context The interpolation context key-value pairs
     *
     * @return string The interpolated string
     */
    protected function interp($string, array $context = array())
    {
        return self::interpolate($string, $context);
    }

    /**
     * Create a new database connection
     *
     * @throws OrmException
     */
    private function createConnection()
    {
        $this->dbType = TypeFactory::create($this);

        $dsn = $this->dbType->getDsn();

        $dsn = $this->interp($dsn, array(
            'host' => $this->host,
            'port' => $this->port,
            'schema' => $this->schema,
            'file' => $this->file
        ));

        try {
            $this->connection = new PDO($dsn, $this->user, $this->password, $this->settings);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->getLog()->info("New instance of PDO connection established");
        } catch (PDOException $ex) {
            throw OrmException::fromPrevious($ex);
        }
    }

    /**
     * Retrieve the database connection
     *
     * @return PDO The database connection
     *
     * @throws OrmException
     */
    public function getConnection()
    {
        if (null == $this->connection) {
            $this->createConnection();
        }

        return $this->connection;
    }

    /**
     * Retrieve the selected schema of the connection
     *
     * @return string The name of schema
     */
    public function getSchema()
    {
        return $this->schema;
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
    private function getTableName($class)
    {
        $parts = explode('\\', $class);
        $simpleClassName = end($parts);
        $tableName = strtolower($simpleClassName);
        $tableName = preg_replace('#model$#', '', $tableName);

        return self::getInstance()->getAnnotatedTableName($class, $tableName);
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
    private function getPrimaryKeyCol($class)
    {
        $instance = self::getInstance();

        $pkColumn = $instance->getAnnotatedPrimaryKeyColumn($class);
        if (! $pkColumn) {
            $pkColumn = $instance->dbType->getPrimaryKeyColumn($this->getTableName($class), $instance);
        }
        if (! $pkColumn) {
            $pkColumn = 'id';
        }

        return $pkColumn;
    }

    /**
     * Retrieve the type of database
     *
     * @return string The type of database
     */
    public function getType()
    {
        return self::getInstance()->type;
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
        $instance = self::getInstance();

        $className = get_called_class();

        $pkColumn = $instance->getPrimaryKeyCol($className);

        $result = self::find(array(
            $pkColumn => $id
        ));

        if (! $result || is_array($result)) {
            throw new OrmException("More than one entity found (expected exactly one)");
        }

        return $result;
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
    private function createQuery(
        $class,
        $tableName,
        array $criteria,
        array $columns,
        $orderBy = '',
        $limit = 0,
        $startFrom = 0
    ) {
        $wheres = array();

        $joins = $this->getAnnotatedQuery($class, $tableName, $this, $criteria, $columns);

        $criterias = array_keys($criteria);

        foreach ($criterias as $criterion) {
            $placeHolder = str_replace('.', '_', $criterion);
            $placeHolder = str_replace('OR ', 'OR_', $placeHolder);
            if (stristr($criteria[$criterion], 'LIKE')) {
                $wheres[] = sprintf("%s LIKE :%s", $criterion, $placeHolder);
            } else {
                $wheres[] = sprintf("%s = :%s", $criterion, $placeHolder);
            }
        }

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

        if ($orderBy && ! stristr($orderBy, 'ORDER BY ')) {
            $orderBy = sprintf("ORDER BY %s", $orderBy);
        }

        $query = sprintf(
            "SELECT %s FROM %s %s %s %s %s",
            implode(',', $columns),
            $tableName,
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
            $instance = self::getInstance();
            $result = $instance->mapAnnotated($from, $toClass);
            $instance->mapReferenced($from, $toClass, $result);
            if ($instance->isEager($toClass)) {
                $instance->injectMappedBy($toClass, $result);
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
    private function mapReferenced($from, $toClass, $result)
    {
        try {
            $rfToClass = new ReflectionClass($toClass);

            foreach (get_object_vars($from) as $property => $value) {
                if (strpos($property, '.')) {
                    list($toProperty, $column) = explode('.', $property);

                    if ($rfToClass->hasProperty($toProperty)) {
                        $referencedClass = $this->getAnnotatedPropertyType($toClass, $toProperty);

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
    private function injectMappedBy($toClass, &$object)
    {
        try {
            $rfToClass = new ReflectionClass($toClass);

            foreach ($rfToClass->getProperties() as $property)
            {
                assert($property instanceof ReflectionProperty);

                if (null != ($parameters = $this->getAnnotatedMappedByParameters($property->getDocComment()))) {
                    $mappedBy = $this->parseMappedBy($parameters);

                    if (null == ($type = $this->getAnnotatedType($property->getDocComment(), $rfToClass->getNamespaceName()))) {
                        throw new OrmException("Can't use mappedBy without specific type for property {property}",
                            array('property' => $property->getName())
                        );
                    }

                    if ($this->isPrimitive($type)) {
                        throw new OrmException("Primitive type can not be used in mappedBy for property {property}",
                            array('property' => $property->getName())
                        );
                    }

                    $getMethod = new ReflectionMethod($toClass, sprintf("get%s", ucfirst($property->getName())));
                    if ($getMethod->invoke($object)) {
                        continue;
                    }

                    $ownPrimaryKey = $this->getPrimaryKey($toClass, $object, true);

                    $otherTable = $this->getTableName($type);
                    $otherPrimaryKeyName = $this->getPrimaryKeyCol($type);
                    $ownPrimaryKeyName =  $this->getPrimaryKeyCol($toClass);

                    $query = sprintf(
                        "SELECT %s.* FROM %s
                        JOIN %s ON %s.%s = %s.%s
                        WHERE %s.%s = :%s",
                        $otherTable, $otherTable,
                        $mappedBy['table'], $mappedBy['table'], $mappedBy['column'], $otherTable, $otherPrimaryKeyName,
                        $mappedBy['table'], $mappedBy['inverseColumn'], $ownPrimaryKeyName
                    );

                    $instance = self::getInstance();

                    try {
                        $connection = $instance->startTX();

                        $statement = $connection->prepare($query);
                        $statement->bindValue(sprintf(":%s", $ownPrimaryKeyName), $ownPrimaryKey);

                        $statement->execute();

                        $result = $statement->fetch(PDO::FETCH_OBJ);

                        $instance->commitTX();

                        $setMethod = new ReflectionMethod($toClass, sprintf("set%s", ucfirst($property->getName())));

                        $setMethod->invoke($object, self::map($result, $type));
                    } catch (PDOException $ex) {
                        $instance->rollBackTX($ex);
                        throw OrmException::fromPrevious($ex);
                    }
                }
            }
        } catch (ReflectionException $ex) {
            throw OrmException::fromPrevious($ex);
        }
    }

    /**
     * Handle a previous occured pdo exception
     *
     * @param PDO $connection The underlying database connection
     * @param PDOStatement $statement The statement which caused the exception to rollback
     * @param Exception $ex The exception cause
     *
     * @return OrmException
     */
    private function handleException(PDO $connection, $statement, Exception $ex, $message = null, $code = 0)
    {
        $toThrow = OrmException::fromPrevious($ex, $message, $code);

        try {
            if ($statement != null) {
                $statement->closeCursor();
            }
            unset($statement);
        } catch (PDOException $cex) {
            // Ignore close cursor exception
        }

        try {
            $this->rollBackTX();
        } catch (PDOException $rbex) {
            $toThrow = new OrmException($rbex->getMessage(), array(), $rbex->getCode(), $toThrow);
        }

        return $toThrow;
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
     * @return mixed Either an array of object, a single object (if only one was found) or null
     *
     * @throws OrmException
     */
    public static function find(array $criteria, $orderBy = "", $limit = 0, $startOffset = 0, $asList = false)
    {
        $results = null;

        $instance = self::getInstance();
        $class = get_called_class();

        $table = $instance->getTableName($class);

        $query = $instance->createQuery(
            $class,
            $table,
            $criteria,
            array(sprintf("%s.*", $table)),
            $orderBy,
            $limit,
            $startOffset
        );
        $statement = null;

        try {
            $connection = $instance->startTX();
            $statement = $connection->prepare($query);

            foreach ($criteria as $criterion => $value) {
                $placeHolder = str_replace('.', '_', $criterion);
                $placeHolder = str_replace('OR ', 'OR_', $placeHolder);
                $value = str_ireplace('LIKE ', '', $value);
                $statement->bindValue(":" . $placeHolder, $value);
            }

            $statement->execute(); // Not good documented, but it seems, that execute() will throw an exception

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
            throw $instance->handleException(
                $instance->getConnection(),
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
    private function getPrimaryKey($class, $object, $onlyValue = false)
    {
        $pk = $this->getAnnotatedPrimaryKey($class, $object, $onlyValue);

        if (null == $pk) {
            $pkCol = $this->getPrimaryKeyCol($class);
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
    private function setPrimaryKey($class, $object, $primaryKey)
    {
        $pkCol = $this->getAnnotatedPrimaryKeyProperty($class);
        if (null == $pkCol) {
            $pkCol = $this->getPrimaryKeyCol($class);
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
    private function persistenceQueryParams($pairs, $primaryKeyCol, $insert = true)
    {
        $query = "";

        $columns = array_keys($pairs);

        if ($insert) {
            $cols = "";
            $vals = "";
            foreach ($columns as $column) {
                //if(!is_null($pairs[$column])) {
                    $cols .= ($cols ? ',' : '');
                    $cols .= $column;
                    $vals .= ($vals ? ',' : '');
                    $vals .= sprintf(':%s', $column);
                //}
            }
            $query = sprintf("(%s) VALUES (%s)", $cols, $vals);
        } else {
            foreach ($columns as $column) {
                if ($column == $primaryKeyCol) {
                    continue;
                }
                $query .= ($query ? ", " : "SET ");
                $query .= sprintf("%s = :%s", $column, $column);
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
    private function persistMappedBy($class, $object)
    {
        $instance = self::getInstance();

        try {
            $rf = new ReflectionClass($class);

            foreach ($rf->getProperties() as $property) {
                assert($property instanceof ReflectionProperty);

                if (null != ($parameters = $this->getAnnotatedMappedByParameters($property->getDocComment()))) {
                    $mappedBy = $this->parseMappedBy($parameters);

                    $method = sprintf("get%s", ucfirst($property->getName()));
                    $rfMethod = new ReflectionMethod($class, $method);
                    assert($rfMethod instanceof ReflectionMethod);
                    $foreignEntity = $rfMethod->invoke($object);

                    if (null !== $foreignEntity) {
                        $foreignPrimaryKey = $this->getPrimaryKey(get_class($foreignEntity), $foreignEntity, true);
                        $ownPrimaryKey = $this->getPrimaryKey($class, $this, true);

                        if (is_null($foreignPrimaryKey)) {
                            throw new OrmException("No primary key column for foreign key found!");
                        }
                        if (is_null($ownPrimaryKey)) {
                            throw new OrmException("No primary key column found!");
                        }

                        $query = sprintf("INSERT INTO %s (%s, %s) VALUES (:%s, :%s)",
                            $mappedBy['table'],
                            $mappedBy['inverseColumn'],
                            $mappedBy['column'],
                            $mappedBy['inverseColumn'],
                            $mappedBy['column']);

                        $connection = $instance->startTX();
                        $statement = null;
                        try
                        {
                            $statement = $connection->prepare($query);
                            $statement->bindValue(sprintf(':%s', $mappedBy['inverseColumn']), $ownPrimaryKey);
                            $statement->bindValue(sprintf(':%s', $mappedBy['column']), $foreignPrimaryKey);

                            $statement->execute();

                            $instance->commitTX();
                        } catch (PDOException $ex) {
                            $instance->rollBackTX($ex);
                            throw $ex;
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

        $class = get_class($this);

        $this->persistAnnotated($class, $this);

        $tableName = $this->getTableName($class);

        $pk = $this->getPrimaryKey($class, $this);
        if (is_null($pk)) {
            throw new OrmException("No primary key column found!");
        }
        foreach ($pk as $primaryKeyCol => $primaryKeyValue) {
            //
        }

        $pairs = $this->getAnnotatedColumnValuePairs($class, $this);

        $query = sprintf("INSERT INTO %s ", $tableName);
        if ($primaryKeyValue) {
            $query = sprintf("UPDATE %s ", $tableName);
        }

        $query .= $this->persistenceQueryParams($pairs, $primaryKeyCol, is_null($primaryKeyValue));

        if ($primaryKeyValue) {
            $query .= sprintf(" WHERE %s = :%s", $primaryKeyCol, $primaryKeyCol);
        }

        $connection = $instance->startTX();
        $statement = null;

        try {
            $statement = $connection->prepare($query);

            foreach ($pairs as $column => $value) {
                if ($value instanceof \Nkey\Caribu\Model\AbstractModel) {
                    $value = $this->getPrimaryKey(get_class($value), $value, true);
                }
                $statement->bindValue(":{$column}", $value);
            }
            if ($primaryKeyValue) {
                $statement->bindValue(":{$primaryKeyCol}", $primaryKeyValue);
            }

            $statement->execute();
            $pk = $connection->lastInsertId();
            unset($statement);

            if (!$primaryKeyValue) {
                $this->setPrimaryKey($class, $this, $pk);
            }

            $this->persistMappedBy($class, $this);

            $instance->commitTX();

        } catch (PDOException $ex) {
            $instance->rollBackTX($ex);
            throw $this->handleException($connection, $statement, $ex, "Persisting data set failed", - 1000);
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

        $class = get_class($this);

        $tableName = $this->getTableName($class);

        $pk = $this->getPrimaryKey($class, $this);
        foreach ($pk as $primaryKeyCol => $primaryKeyValue) {
            //
        }

        if (is_null($primaryKeyValue)) {
            throw new OrmException("Entity is not persisted or detached. Can not delete.");
        }

        $connection = $instance->startTX();
        $statement = null;
        $query = sprintf("DELETE FROM %s WHERE %s = :%s", $tableName, $primaryKeyCol, $primaryKeyCol);

        try {

            $statement = $connection->prepare($query);
            $statement->bindValue(":{$primaryKeyCol}", $primaryKeyValue);
            $statement->execute();
            unset($statement);
            $instance->commitTX();
        } catch (PDOException $ex) {
            $instance->rollBackTX($ex);
            throw $this->handleException($connection, $statement, $ex, "Persisting data set failed", - 1000);
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

    /**
     * Begin a new transaction
     *
     * @return PDO
     */
    private function startTX()
    {
        if (null == $this->connection) {
            $this->connection = $this->getConnection();
        }

        if (!$this->connection->inTransaction()) {
            $this->connection->beginTransaction();
        }

        $this->transactionStack++;

        return $this->connection;
    }

    /**
     * Try to commit the complete transaction stack
     *
     * @throws OrmException
     * @throws PDOException
     */
    private function commitTX()
    {
        if (!$this->connection->inTransaction()) {
            throw new OrmException("Transaction is not open");
        }

        $this->transactionStack--;

        if ($this->transactionStack === 0) {
            $this->connection->commit();
        }
    }

    /**
     * Rollback the complete stack
     *
     * @throws OrmException
     */
    private function rollBackTX(Exception $previousException = null)
    {
        $this->transactionStack = 0; // Yes, we just ignore any error and reset the transaction stack here

        if (!$this->connection->inTransaction()) {
            throw new OrmException("Transaction not open", array(), 102, $previousException);
        }

        try {
            if (!$this->connection->rollBack()) {
                throw new OrmException("Could not rollback!", array(), 103, $previousException);
            }
        }
        catch (PDOException $ex) {
            throw OrmException::fromPrevious($ex);
        }
    }
}
