<?php
namespace Nkey\Caribu\Tests;

require_once dirname(__FILE__).'/../MySqlAbstractDatabaseTestCase.php';

use Nkey\Caribu\Orm\Orm;

use Nkey\Caribu\Tests\Model\BlogPost;
use Nkey\Caribu\Tests\Model\BlogUser;

/**
 * Entity with list of referenced entities
 * test cases (mysql is used)
 *
 * This class is part of Caribu package
 *
 * @author Maik Greubel <greubel@nkey.de>
 */
class MySQLEntityListTest extends MySqlAbstractDatabaseTestCase
{
    public function __construct()
    {
        parent::__construct();

        $this->dataSetFile = dirname(__FILE__).'/../_files/blog-seed.xml';
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
        $connection->exec("DROP TABLE IF EXISTS `blog`");
        $connection->exec("DROP TABLE IF EXISTS `user`");
        $connection->exec("DROP TABLE IF EXISTS `blog_user_to_posts`");
        $connection->exec("CREATE TABLE `blog` (`id` INTEGER PRIMARY KEY AUTO_INCREMENT, `content` TEXT, `created` TEXT)");
        $connection->exec("CREATE TABLE `user` (`id` INTEGER PRIMARY KEY AUTO_INCREMENT, `name` TEXT, `email` TEXT)");
        $connection->exec("CREATE TABLE `blog_user_to_posts` (`id` INTEGER PRIMARY KEY AUTO_INCREMENT, `userid` INTEGER, `postid` INTEGER)");
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
        $connection->exec("DROP TABLE `blog_user_to_posts`");
        $connection->exec("DROP TABLE `user`");
        $connection->exec("DROP TABLE `blog`");
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

    public function testStoreReferenced()
    {
        $joe = BlogUser::find(array('name' => 'joe'));

        $this->assertFalse(is_null($joe));

        $post = new BlogPost();
        $time = time();
        $post->setCreated($time);
        $post->setContent('New content');
        $post->setUser($joe);

        $post->persist();

        $post = BlogPost::find(array('created' => $time));
        $this->assertFalse(is_null($post->getUser()));
        $this->assertEquals('joe', $post->getUser()->getName());
    }
}