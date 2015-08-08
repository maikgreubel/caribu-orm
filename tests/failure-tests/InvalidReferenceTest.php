<?php
namespace Nkey\Caribu\Tests;

require_once dirname(__FILE__).'/../AbstractDatabaseTestCase.php';
require_once dirname(__FILE__).'/../Model/InvalidReferenceModel.php';

use Nkey\Caribu\Tests\Model\InvalidReferenceModel;
use Nkey\Caribu\Orm\Orm;

class InvalidReferenceTest extends AbstractDatabaseTestCase
{
    public function __construct()
    {
        $this->options = array(
            'type' => 'sqlite',
            'file' => ':memory:'
        );
    }

    /**
     * (non-PHPdoc)
     * @see PHPUnit_Extensions_Database_TestCase::setUp()
     */
    protected function setUp()
    {
        Orm::passivate();

        $connection = $this->getConnection()->getConnection();
        $connection->beginTransaction();
        $connection->exec("CREATE TABLE blog (id INTEGER, content TEXT)");
        $connection->exec("INSERT INTO blog VALUES (1, 'Some content')");
        $connection->commit();

        parent::setUp();
    }

    /**
     * (non-PHPdoc)
     * @see PHPUnit_Extensions_Database_TestCase::tearDown()
     */
    protected function tearDown()
    {
        $connection = $this->getConnection()->getConnection();
        $connection->beginTransaction();
        $connection->exec("DROP TABLE blog");
        $connection->commit();

        parent::tearDown();
    }


    /**
     * @expectedException \Nkey\Caribu\Orm\OrmException
     * @expectedExceptionMessageRegex Annotated type \w+ could not be found nor loaded
     */
    public function testInvalidMappedBy()
    {
        InvalidReferenceModel::find(array('id' => 1));
    }

}