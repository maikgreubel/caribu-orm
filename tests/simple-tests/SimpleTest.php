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
 * Simple test cases
 *
 * This class is part of Caribu package
 *
 * @author Maik Greubel <greubel@nkey.de>
 */
class SimpleTest extends AbstractDatabaseTestCase
{
    public function __construct()
    {
        $this->options = array(
            'type' => 'sqlite',
            'file' => ':memory:'
        );

        $this->flatDataSetFile = dirname(__FILE__).'/../_files/guestbook-seed.xml';
    }

    /**
     * (non-PHPdoc)
     * @see PHPUnit_Extensions_Database_TestCase::setUp()
     */
    protected function setUp()
    {
        $connection = $this->getConnection()->getConnection();
        $connection->beginTransaction();
        $connection->query("CREATE TABLE guestbook (id INTEGER PRIMARY KEY AUTOINCREMENT, content TEXT, user TEXT, created TEXT)");
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
        $connection->query("DROP TABLE guestbook");
        $connection->commit();

        parent::tearDown();
    }

    /**
     * Test simple
     */
    public function testSimple()
    {
        $model = new MockedModel();
        $this->assertFalse($model->getConnection() == null);
    }

    /**
     * Test fetching
     */
    public function testFetching()
    {
        $entity = GuestBookModel::get(1);
        $this->assertFalse(is_null($entity));
        $this->assertEquals("joe", $entity->getUser());
    }

    /**
     * Test annotated fetching
     */
    public function testAnnotated()
    {
        $entity = AnnotatedGuestBookModel::get(1);
        $this->assertFalse(is_null($entity));
        $this->assertEquals(1, $entity->getGid());
        $this->assertEquals("joe", $entity->getUser());
    }

    /**
     * Test finding
     */
    public function testFind()
    {
        $entity = AnnotatedGuestBookModel::find(array('user' => 'joe'), 'id ASC', 1);
        $this->assertFalse(is_null($entity));
        $this->assertEquals(1, $entity->getGid());
        $this->assertEquals("joe", $entity->getUser());
    }
}