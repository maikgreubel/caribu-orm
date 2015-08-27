<?php
namespace Nkey\Caribu\Tests;

require_once dirname(__FILE__).'/../PostgresAbstractDatabaseTestCase.php';
require_once dirname(__FILE__).'/../Model/MockedModel.php';
require_once dirname(__FILE__).'/../Model/GuestBookModel.php';
require_once dirname(__FILE__).'/../Model/AnnotatedGuestBookModel.php';

use Nkey\Caribu\Tests\Model\MockedModel;
use Nkey\Caribu\Tests\Model\GuestBookModel;
use Nkey\Caribu\Tests\Model\AnnotatedGuestBookModel;

use Nkey\Caribu\Tests\PostgresAbstractDatabaseTestCase;
use Nkey\Caribu\Orm\Orm;

/**
 * Complex test cases (postgres is used)
 *
 * @author Maik Greubel <greubel@nkey.de>
 *         This class is part of Caribu package
 */
class PostgresComplexTest extends PostgresAbstractDatabaseTestCase
{
    public function __construct()
    {
        parent::__construct();

        $this->flatDataSetFile = dirname(__FILE__).'/../_files/guestbook-seed.xml';
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
        $connection->exec("DROP TABLE IF EXISTS guestbook");
        $connection->exec("DROP SEQUENCE IF EXISTS seq_guestbook_id");
        $connection->exec("CREATE SEQUENCE seq_guestbook_id START WITH 100");
        $connection->exec("CREATE TABLE guestbook (id INTEGER PRIMARY KEY DEFAULT NEXTVAL('seq_guestbook_id'), \"content\" TEXT, \"user\" CHARACTER(50), \"created\" CHARACTER(50))");
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
        $connection->exec("DROP TABLE guestbook");
        $connection->commit();

        parent::tearDown();
    }

    /**
     * Test saving entity
     */
    public function testSave()
    {
        $entity = GuestBookModel::find(array('user' => 'alice'));
        $this->assertTrue(is_null($entity));

        $entity = new GuestBookModel();
        $entity->setUser('alice');
        $entity->setCreated(time());
        $entity->setContent("Another nonsense posting");

        $entity->persist();

        $entity = GuestBookModel::find(array('user' => 'alice'));
        $this->assertFalse(is_null($entity));
        $this->assertFalse(is_null($entity->getId()));
    }

    /**
     * Test removing entity
     */
    public function testRemove()
    {
        $entity = AnnotatedGuestBookModel::get(1);
        $this->assertFalse(is_null($entity));
        $entity->delete();

        $entity = AnnotatedGuestBookModel::find(array('id' => 1));
        $this->assertTrue(is_null($entity));
    }
}