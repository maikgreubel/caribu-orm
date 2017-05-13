<?php
namespace Nkey\Caribu\Tests;

use Nkey\Caribu\Orm\Orm;

/**
 * Test case abstraction
 *
 * This class is part of Caribu package
 *
 * @author Maik Greubel <greubel@nkey.de>
 */
abstract class AbstractDatabaseTestCase extends \PHPUnit\DbUnit\TestCase
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
     * @var \PHPUnit\DbUnit\Database\Connection
     */
    private $connection = null;

    /**
     * Dataset for testing
     *
     * @var \PHPUnit\DbUnit\DataSet\IDataSet
     */
    private $dataset;

    /**
     * (non-PHPdoc)
     * @see \PHPUnit\DbUnit\TestCase::getConnection()
     */
    public function getConnection()
    {
        if(null == $this->connection) {
            Orm::configure($this->options);
            $this->connection = new \PHPUnit\DbUnit\Database\DefaultConnection(Orm::getInstance()->getConnection());
        }

        return $this->connection;
    }

    /**
     * (non-PHPdoc)
     * @see \PHPUnit\DbUnit\TestCase::getDataSet()
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
            	$this->dataset = new \PHPUnit\DbUnit\DataSet\DefaultDataSet();
            }
        }
        return $this->dataset;
    }
}
