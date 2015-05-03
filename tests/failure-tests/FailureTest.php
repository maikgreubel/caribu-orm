<?php
namespace Nkey\Caribu\Tests;

require_once dirname(__FILE__).'/../AbstractDatabaseTestCase.php';
require_once dirname(__FILE__).'/../Model/NoteModel.php';

use Nkey\Caribu\Tests\Model\NoteModel;

use Nkey\Caribu\Tests\AbstractDatabaseTestCase;
use Nkey\Caribu\Orm\Orm;
use Nkey\Caribu\Orm\OrmException;
use Nkey\Caribu\Tests\Model\Nkey\Caribu\Tests\Model;

/**
 * Failure test cases
 *
 * This class is part of Caribu package
 *
 * @author Maik Greubel <greubel@nkey.de>
 */
class FailureTest extends AbstractDatabaseTestCase
{
    public function __construct()
    {
        $this->options = array(
            'type' => 'sqlite',
            'file' => ':memory:'
        );

        $this->dataSetFile = dirname(__FILE__).'/../_files/notes-seed.xml';
    }

    /**
     * (non-PHPdoc)
     * @see PHPUnit_Extensions_Database_TestCase::setUp()
     */
    protected function setUp()
    {
        $connection = $this->getConnection()->getConnection();
        $connection->beginTransaction();
        $connection->exec("CREATE TABLE notes (id INTEGER, content TEXT, created TEXT)");
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
        $connection->exec("DROP TABLE notes");
        $connection->commit();

        parent::tearDown();
    }

    /**
     * Test missing primary key
     *
     * @expectedException Nkey\Caribu\Orm\OrmException
     * @expectedExceptionMessage Exception ReflectionException occured:
     */
    public function testMissingPrimaryKey()
    {
        $entity = new NoteModel();
        $entity->setContent("Will fail");
        $entity->setCreated(time());
        $entity->persist();
    }
}
