<?php
namespace Nkey\Caribu\Tests;

require_once dirname(__FILE__).'/../AbstractDatabaseTestCase.php';
require_once dirname(__FILE__).'/../Model/NoteModel.php';

use Nkey\Caribu\Tests\Model\InvalidModel;

use Nkey\Caribu\Orm\Orm;

/**
 * Invalid model test cases
 *
 * This class is part of Caribu package
 *
 * @author Maik Greubel <greubel@nkey.de>
 */
class InvalidModelTest extends AbstractDatabaseTestCase
{
    public function __construct()
    {
    	parent::__construct();
    	
        $this->options = array(
            'type' => 'sqlite',
            'file' => ':memory:'
        );
    }

    /**
     * (non-PHPdoc)
     * @see \PHPUnit\DbUnit\TestCase::setUp()
     */
    protected function setUp()
    {
        Orm::passivate();

        $connection = $this->getConnection()->getConnection();
        $connection->beginTransaction();
        $connection->exec("CREATE TABLE users (id INTEGER, username TEXT)");
        $connection->commit();

        parent::setUp();
    }

    /**
     * (non-PHPdoc)
     * @see \PHPUnit\DbUnit\TestCase::tearDown()
     */
    protected function tearDown()
    {
        $connection = $this->getConnection()->getConnection();
        $connection->beginTransaction();
        $connection->exec("DROP TABLE users");
        $connection->commit();

        parent::tearDown();
    }

    /**
     * @expectedException Nkey\Caribu\Orm\OrmException
     * @expectedExceptionMessage Exception PDOException occured: Persisting data set failed
     */
    public function testPersistingFail()
    {
        $entity = new InvalidModel();
        $entity->setUsername('Joe');
        $entity->persist();
    }
}
