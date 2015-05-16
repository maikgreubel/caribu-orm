<?php
namespace Nkey\Caribu\Tests;

use Nkey\Caribu\Orm\Orm;
use Nkey\Caribu\Tests\Model\MockedModel;

use \PHPUnit_Extensions_Database_TestCase;

/**
 * Test case abstraction
 *
 * This class is part of Caribu package
 *
 * @author Maik Greubel <greubel@nkey.de>
 */
abstract class AbstractDatabaseTestCase extends PHPUnit_Extensions_Database_TestCase
{
    /**
     * Options for database connection configuration
     *
     * @var array
     */
    protected $options;

    /**
     * File for loading the test flat data sets
     *
     * @var string
     */
    protected $flatDataSetFile;

    /**
     * File for loading the test non-flat data sets
     *
     * @var string
     */
    protected $dataSetFile;

    /**
     * Database connection
     *
     * @var PHPUnit_Extensions_Database_DB_IDatabaseConnection
     */
    private $connection = null;

    /**
     * Dataset for testing
     *
     * @var PHPUnit_Extensions_Database_DataSet_IDataSet
     */
    private $dataset;

    /**
     * (non-PHPdoc)
     * @see PHPUnit_Extensions_Database_TestCase::getConnection()
     */
    public function getConnection()
    {
        if(null == $this->connection) {
            Orm::configure($this->options);
            $this->connection = new \PHPUnit_Extensions_Database_DB_DefaultDatabaseConnection(Orm::getInstance()->getConnection());
        }

        return $this->connection;
    }

    /**
     * (non-PHPdoc)
     * @see PHPUnit_Extensions_Database_TestCase::getDataSet()
     */
    public function getDataSet()
    {
        if(null == $this->dataset) {
            if($this->flatDataSetFile) {
                $this->dataset = $this->createFlatXMLDataSet($this->flatDataSetFile);
            }
            else if($this->dataSetFile) {
                $this->dataset = $this->createXmlDataSet($this->dataSetFile);
            }
            else {
                $this->dataset = new \PHPUnit_Extensions_Database_DataSet_DefaultDataSet();
            }
        }
        return $this->dataset;
    }

}
