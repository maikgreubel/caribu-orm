<?php
namespace Nkey\Caribu\Tests;

require_once dirname(__FILE__).'/../MySqlAbstractDatabaseTestCase.php';
require_once dirname(__FILE__).'/../Model/ReferencedGuestBook.php';
require_once dirname(__FILE__).'/../Model/User.php';

use Nkey\Caribu\Orm\Orm;

use Nkey\Caribu\Tests\MySqlAbstractDatabaseTestCase;
use Nkey\Caribu\Tests\Model\ReferencedGuestBook;
use Nkey\Caribu\Tests\Model\User;

class MySQLPersistComplexTypesTest extends MySqlAbstractDatabaseTestCase
{
    public function __construct()
    {
        parent::__construct();

        $this->dataSetFile = dirname(__FILE__).'/../_files/referenced-guestbook-seed.xml';
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
        $connection->exec("DROP TABLE IF EXISTS `guestbook`");
        $connection->exec("DROP TABLE IF EXISTS `user`");
        $connection->exec("CREATE TABLE `user` (`uid` INTEGER PRIMARY KEY AUTO_INCREMENT, `name` VARCHAR(50), `email` VARCHAR(50))");
        $connection->exec("CREATE TABLE `guestbook` (`id` INTEGER PRIMARY KEY AUTO_INCREMENT, `content` TEXT, `user` TEXT, `created` TIMESTAMP)");
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
        $connection->exec("DROP TABLE `guestbook`");
        $connection->exec("DROP TABLE `user`");
        $connection->commit();

        parent::tearDown();
    }

    public function testPersist()
    {
        $user = User::find(array('name' => 'bob'));

        $entity = new ReferencedGuestBook();
        $entity->setCreated(new \DateTime());
        $entity->setContent("Some test content to persist");
        $entity->setUser($user);

        $entity->persist();

        $this->assertFalse(is_null($entity->getGid()));

        $second = ReferencedGuestBook::get($entity->getGid());

        $this->assertEquals($entity->getCreated(), $second->getCreated());
    }
}