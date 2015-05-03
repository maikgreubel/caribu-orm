<?php
namespace Nkey\Caribu\Tests;

require_once dirname(__FILE__).'/../AbstractDatabaseTestCase.php';
require_once dirname(__FILE__).'/../Model/MockedModel.php';
require_once dirname(__FILE__).'/../Model/GuestBookModel.php';
require_once dirname(__FILE__).'/../Model/AnnotatedGuestBookModel.php';

use Nkey\Caribu\Tests\Model\MockedModel;
use Nkey\Caribu\Tests\Model\GuestBookModel;
use Nkey\Caribu\Tests\Model\AnnotatedGuestBookModel;

use Nkey\Caribu\Tests\AbstractDatabaseTestCase;
use Nkey\Caribu\Orm\Orm;

/**
 * Complex test cases
 *
 * This class is part of Caribu package
 *
 * @author Maik Greubel <greubel@nkey.de>
 */
class ComplexTest extends AbstractDatabaseTestCase
{
    public function __construct()
    {
        $this->options = array(
            'type' => 'sqlite',
            'file' => ':memory:'
        );

        $this->dataSetFile = dirname(__FILE__).'/../_files/guestbook-seed.xml';
    }

    /**
     * (non-PHPdoc)
     * @see PHPUnit_Extensions_Database_TestCase::setUp()
     */
    protected function setUp()
    {
        $connection = $this->getConnection()->getConnection();
        $connection->beginTransaction();
        $connection->exec("CREATE TABLE guestbook (id INTEGER PRIMARY KEY AUTOINCREMENT, content TEXT, user TEXT, created TEXT)");
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