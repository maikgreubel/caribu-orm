<?php
namespace Nkey\Caribu\Tests;

require_once dirname(__FILE__).'/../AbstractDatabaseTestCase.php';

use Nkey\Caribu\Orm\Orm;

use Nkey\Caribu\Tests\AbstractDatabaseTestCase;
use Nkey\Caribu\Tests\Model\BlogPost;

/**
 * Entity with list of referenced entities
 * test cases (sqlite is used)
 *
 * This class is part of Caribu package
 *
 * @author Maik Greubel <greubel@nkey.de>
 */
class EnityListTest extends AbstractDatabaseTestCase
{
    public function __construct()
    {
        $this->options = array(
            'type' => 'sqlite',
            'file' => ':memory:'
        );

        $this->dataSetFile = dirname(__FILE__).'/../_files/blog-seed.xml';
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
        $connection->exec("CREATE TABLE blog (id INTEGER PRIMARY KEY AUTOINCREMENT, content TEXT, created TEXT)");
        $connection->exec("CREATE TABLE user (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, email TEXT)");
        $connection->exec("CREATE TABLE blog_user_to_posts (id INTEGER PRIMARY KEY AUTOINCREMENT, userid INTEGER, postid INTEGER)");
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
        $connection->exec("DROP TABLE blog_user_to_posts");
        $connection->exec("DROP TABLE user");
        $connection->exec("DROP TABLE blog");
        $connection->commit();

        parent::tearDown();
    }

    public function testReadPosts()
    {
        $posts = BlogPost::find(array('user.name' => 'joe'));
        $this->assertEquals(2, count($posts));

        $this->assertFalse(is_null($posts[0]->getUser()));
        $this->assertEquals('joe', $posts[0]->getUser()->getName());

        $this->assertFalse(is_null($posts[1]->getUser()));
        $this->assertEquals('joe', $posts[1]->getUser()->getName());
    }
}