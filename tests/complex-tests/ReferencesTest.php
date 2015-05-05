<?php
namespace Nkey\Caribu\Tests;

require_once dirname(__FILE__).'/../AbstractDatabaseTestCase.php';
require_once dirname(__FILE__).'/../Model/ReferencedGuestBook.php';
require_once dirname(__FILE__).'/../Model/User.php';

use Nkey\Caribu\Tests\AbstractDatabaseTestCase;
use Nkey\Caribu\Orm\Orm;

use Nkey\Caribu\Tests\Model\ReferencedGuestBook;
use Nkey\Caribu\Tests\Model\User;

/**
 * Complex test cases
 *
 * This class is part of Caribu package
 *
 * @author Maik Greubel <greubel@nkey.de>
 */
class ReferencesTest extends AbstractDatabaseTestCase
{
    public function __construct()
    {
        $this->options = array(
            'type' => 'sqlite',
            'file' => ':memory:'
        );

        $this->dataSetFile = dirname(__FILE__).'/../_files/referenced-guestbook-seed.xml';
    }

    /**
     * (non-PHPdoc)
     * @see PHPUnit_Extensions_Database_TestCase::setUp()
     */
    protected function setUp()
    {
        $connection = $this->getConnection()->getConnection();
        $connection->beginTransaction();
        $connection->exec("CREATE TABLE guestbook (id INTEGER PRIMARY KEY AUTOINCREMENT, content TEXT, user INTEGER, created TEXT)");
        $connection->exec("CREATE TABLE user (uid INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, email TEXT)");
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
        $connection->exec("DROP TABLE user");
        $connection->exec("DROP TABLE guestbook");
        $connection->commit();

        parent::tearDown();
    }

    public function testReferencedGet()
    {
        $entity = ReferencedGuestBook::get(1);
        $this->assertFalse(is_null($entity));
        $this->assertFalse(is_null($entity->getUser()));
        $this->assertTrue($entity->getUser() instanceof User);
        $this->assertEquals(1, $entity->getUser()->getId());
    }
}