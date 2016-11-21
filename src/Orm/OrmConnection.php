<?php
namespace Nkey\Caribu\Orm;

/**
 * Connection related functionality for the ORM
 *
 * This class is part of Caribu package
 *
 * @author Maik Greubel <greubel@nkey.de>
 */
trait OrmConnection
{
    /**
     * Include logging facility
     */
    use \Generics\Logger\LoggerTrait;

    /**
     * The database connection
     *
     * @var \PDO
     */
    private $connection = null;

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
     * Database type
     *
     * @var \Nkey\Caribu\Type\IType
     */
    private $dbType = null;

    /**
     * Configure the Orm
     *
     * @param array $options
     *            Various options to use for configuration. See documentation for details.
     */
    public static function configure($options = array())
    {
        self::parseOptions($options);
    }

    /**
     * Assign the option to local configuration
     *
     * @param string $option
     *            The option to assign
     * @param mixed $value
     *            The value to assign to option
     */
    private static function assignOption(string $option, $value)
    {
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

    /**
     * Parse the options
     *
     * @param array $options
     *            The options to parse
     */
    private static function parseOptions($options)
    {
        foreach ($options as $option => $value) {
            self::assignOption($option, $value);
        }
    }

    /**
     * Retrieve the database type
     *
     * @return \Nkey\Caribu\Type\IType
     */
    public function getDbType()
    {
        if (null == $this->dbType) {
            $this->dbType = \Nkey\Caribu\Type\TypeFactory::create($this);
        }
        return $this->dbType;
    }

    /**
     * Create a new database connection
     *
     * @throws OrmException
     */
    private function createConnection()
    {
        $dsn = $this->getDbType()->getDsn();
        
        $dsn = self::interpolate($dsn, array(
            'host' => $this->host,
            'port' => ($this->port ? $this->port : $this->dbType->getDefaultPort()),
            'schema' => $this->schema,
            'file' => $this->file
        ));
        
        try {
            $this->connection = new \PDO($dsn, $this->user, $this->password, $this->settings);
            $this->connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $this->getLog()->info("New instance of PDO connection established");
        } catch (\PDOException $exception) {
            throw OrmException::fromPrevious($exception, $exception->getMessage(), $exception->getCode());
        }
    }

    /**
     * Retrieve the database connection
     *
     * @return \PDO The database connection
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
     * Retrieve the type of database
     *
     * @return string The type of database
     */
    public function getType()
    {
        return self::getInstance()->type;
    }

    /**
     * Detach connection
     */
    private function passivateConnection()
    {
        unset($this->connection);
    }
}
