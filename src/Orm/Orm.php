<?php
namespace Nkey\Caribu\Orm;

use Generics\Logger\LoggerTrait;
use Generics\Util\Interpolator;
use Nkey\Caribu\Model\AbstractModel;
use Nkey\Caribu\Type\AbstractType;
use Nkey\Caribu\Type\TypeFactory;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\ConsoleOutput;
use \Exception;
use \PDO;
use \PDOException;
use \PDOStatement;
use \ReflectionMethod;
use \ReflectionException;

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
    private static $instance;

    /**
     * The concrete database type
     *
     * @var string
     */
    private $type;

    /**
     * The database schema
     *
     * @var string
     */
    private $schema;

    /**
     * Database connection user
     *
     * @var string
     */
    private $user;

    /**
     * Database connection password
     *
     * @var string
     */
    private $password;

    /**
     * Database connection host
     *
     * @var string
     */
    private $host;

    /**
     * Database connection port
     *
     * @var int
     */
    private $port;

    /**
     * Embedded database file
     *
     * @var string
     */
    private $file;

    /**
     * Settings to use for connection
     *
     * @var array
     */
    private $settings;

    /**
     * The database connection
     *
     * @var PDO
     */
    private $connection;

    /**
     * Database type
     *
     * @var AbstractType
     */
    private $dbType;

    /**
     * Singleton pattern
     *
     * @return \Nkey\Caribu\Orm\Orm
     */
    public static function getInstance()
    {
        if (null == self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Singleton pattern
     */
    private function __construct()
    {
        $this->setLogger(new ConsoleLogger(new ConsoleOutput()));
    }

    /**
     * Disable cloning
     */
    private function __clone()
    {}

    /**
     * Configure the Orm
     *
     * @param array $options
     */
    public static function configure($options = array())
    {
        self::parseOptions($options);
    }

    /**
     * Parse the options
     *
     * @param array $options
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
     * @param string $string
     * @param array $context
     *
     * @return string
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
     * @return PDO
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
     * Get the name of table
     *
     * @param string $class
     * @return string
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
     * @param string $class
     * @return string
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
     * @return string
     */
    public static function getType()
    {
        return self::getInstance()->type;
    }

    /**
     * Retrieve particular dataset by given id
     *
     * @param mixed $id
     *
     * @return AbstractModel
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
     * @param array $criteria
     * @param string $columns
     * @param string $orderBy
     * @param number $limit
     * @param number $startFrom
     * @return string
     */
    private function createQuery($tableName, $criteria, $columns = '*', $orderBy = '', $limit = 0, $startFrom = 0)
    {
        $wheres = array();

        $criterias = array_keys($criteria);

        foreach ($criterias as $criterion) {
            $wheres[] = sprintf("%s = :%s", $criterion, $criterion);
        }

        if (count($wheres)) {
            $wheres = sprintf("WHERE %s", implode(' AND ', $wheres));
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

        $query = sprintf("SELECT %s FROM %s %s %s %s", $columns, $tableName, $wheres, $orderBy, $limits);

        return $query;
    }

    /**
     * Map a object from default class into specific
     *
     * @param stdClass $from
     * @param string $toClass
     *
     * @return object
     *
     * @throws OrmException
     */
    private static function map($from, $toClass)
    {
        $result = null;
        try {
            $instance = self::getInstance();
            $result = $instance->mapAnnotated($from, $toClass);
        } catch (OrmException $ex) {
            // TODO: implement simple handling without annotation
            throw $ex;
        }

        return $result;
    }

    /**
     * Handle a previous occured pdo exception
     *
     * @param PDO $connection
     * @param PDOStatement $statement
     * @param Exception $ex
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
            $connection->rollBack();
        } catch (PDOException $rbex) {
            $toThrow = new OrmException($rbex->getMessage(), array(), $rbex->getCode(), $toThrow);
        }

        return $toThrow;
    }

    /**
     * Find data sets by given criteria
     *
     * @param array $criteria
     * @param string $orderBy
     * @param int $limit
     * @param int $startOffset
     *
     * @return mixed
     *
     * @throws OrmException
     */
    public static function find(array $criteria, $orderBy = "", $limit = 0, $startOffset = 0)
    {
        $results = null;

        $instance = self::getInstance();
        $class = get_called_class();

        $query = $instance->createQuery($instance->getTableName($class), $criteria, '*', $orderBy, $limit, $startOffset);
        $statement = null;

        try {
            $instance->connection->beginTransaction();
            $statement = $instance->connection->prepare($query);

            if (! $statement) {
                throw new OrmException("Could not prepare statement for query {query}", array(
                    'query' => $query
                ));
            }

            foreach ($criteria as $criterion => $value) {
                $statement->bindValue(":" . $criterion, $value);
            }

            if (! $statement->execute()) {
                throw new OrmException("Could not execute query {query}", array(
                    'query' => $query
                ));
            }

            $unmapped = array();
            while ($result = $statement->fetch(PDO::FETCH_OBJ)) {
                $unmapped[] = $result;
            }

            $statement->closeCursor();
            $instance->connection->commit();

            foreach ($unmapped as $result) {
                $results[] = self::map($result, $class);
            }

            if (count($results) == 1) {
                $results = $results[0];
            }
        } catch (OrmException $ex) {
            throw $instance->handleException($instance->connection, $statement, $ex, "Finding data set failed", - 100);
        } catch (PDOException $ex) {
            throw $instance->handleException($instance->connection, $statement, $ex, "Finding data set failed", - 100);
        }

        return $results;
    }

    /**
     * Retrieve the primary key value
     *
     * @param string $class
     *
     * @return array
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
                if(!$onlyValue) {
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
     * @param string $class
     * @param AbstractModel $object
     * @param mixed $primaryKey
     * @throws OrmException
     */
    private function setPrimaryKey($class, $object, $primaryKey)
    {
        $pkCol = $this->getAnnotatedPrimaryKeyProperty($class);
        if(null == $pkCol) {
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
     * @param array $pairs
     * @return string
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
        foreach ($pk as $primaryKeyCol => $primaryKeyValue) {}

        $pairs = $this->getAnnotatedColumnValuePairs($class, $this);

        $query = sprintf("INSERT INTO %s ", $tableName);
        if ($primaryKeyValue) {
            $query = sprintf("UPDATE %s ", $tableName);
        }

        $query .= $this->persistenceQueryParams($pairs, $primaryKeyCol, is_null($primaryKeyValue));

        if ($primaryKeyValue) {
            $query .= sprintf(" WHERE %s = :%s", $primaryKeyCol, $primaryKeyCol);
        }

        $connection = $instance->getConnection();
        $statement = null;

        try {
            $connection->beginTransaction();

            $statement = $connection->prepare($query);

            foreach ($pairs as $column => $value) {
                if ($value instanceof AbstractModel) {
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
            $connection->commit();

            if(!$primaryKeyValue) {
                $this->setPrimaryKey($class, $this, $pk);
            }
        } catch (PDOException $ex) {
            $connection->rollBack();
            throw $this->handleException($connection, $statement, $ex, "Persisting data set failed", - 1000);
        }
    }

    /**
     * Removes a persisted entity
     *
     * @throws OrmException
     */
    public function delete()
    {
        $instance = self::getInstance();

        $class = get_class($this);

        $tableName = $this->getTableName($class);

        $pk = $this->getPrimaryKey($class, $this);
        foreach ($pk as $primaryKeyCol => $primaryKeyValue) {}

        if (is_null($primaryKeyValue)) {
            throw new OrmException("Entity is not persisted or detached. Can not delete.");
        }

        $connection = $instance->getConnection();
        $statement = null;
        $query = sprintf("DELETE FROM %s WHERE %s = :%s", $tableName, $primaryKeyCol, $primaryKeyCol);

        try {
            $connection->beginTransaction();

            $statement = $connection->prepare($query);
            $statement->bindValue(":{$primaryKeyCol}", $primaryKeyValue);
            $statement->execute();
            unset($statement);
            $connection->commit();
        } catch (PDOException $ex) {
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
}