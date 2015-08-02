<?php
namespace Nkey\Caribu\Tests;

require_once dirname(__FILE__).'/../AbstractDatabaseTestCase.php';
require_once dirname(__FILE__).'/../Model/User.php';

use Nkey\Caribu\Tests\Model\User;

use Nkey\Caribu\Tests\AbstractDatabaseTestCase;
use Nkey\Caribu\Orm\Orm;
use Nkey\Caribu\Orm\OrmException;

/**
 * Duplicate id test cases
 *
 * This class is part of Caribu package
 *
 * @author Maik Greubel <greubel@nkey.de>
 */
class DuplicateIdTest extends AbstractDatabaseTestCase
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
        $connection->exec("CREATE TABLE user (uid INTEGER, name TEXT, email TEXT)");
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
        $connection->commit();

        parent::tearDown();
    }

    /**
     * @expectedException Nkey\Caribu\Orm\OrmException
     * @expectedExceptionMessage More than one entity found (expected exactly one)
     */
    public function testDuplicateId()
    {
        $user = new User();
        $user->setId(1);
        $user->setName('joe');
        $user->setEmail('joe@test.tld');
        $user->persist();

        $user = new User();
        $user->setId(1);
        $user->setName('jane');
        $user->setEmail('jane@test.tld');
        $user->persist();

        User::get(1);
    }

}
