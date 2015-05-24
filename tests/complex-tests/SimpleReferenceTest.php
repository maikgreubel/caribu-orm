<?php
namespace Nkey\Caribu\Tests;

require_once dirname(__FILE__).'/../AbstractDatabaseTestCase.php';

use Nkey\Caribu\Orm\Orm;

use Nkey\Caribu\Tests\AbstractDatabaseTestCase;
use Nkey\Caribu\Tests\Model\Author;
use Nkey\Caribu\Tests\Model\Book;

/**
 * Relationship test cases
 *
 * This class is part of Caribu package
 *
 * @author Maik Greubel <greubel@nkey.de>
 */
class SimpleReferenceTest extends MySqlAbstractDatabaseTestCase
{

    /**
     * (non-PHPdoc)
     *
     * @see PHPUnit_Extensions_Database_TestCase::setUp()
     */
    protected function setUp()
    {
        Orm::passivate();

        $connection = $this->getConnection()->getConnection();
        $connection->beginTransaction();
        $connection->exec("DROP TABLE IF EXISTS `books`");
        $connection->exec("DROP TABLE IF EXISTS `authors`");
        $connection->exec("CREATE TABLE `authors` (`id` INTEGER PRIMARY KEY AUTO_INCREMENT, `name` TEXT)");
        $connection->exec("CREATE TABLE `books` (`id` INTEGER PRIMARY KEY AUTO_INCREMENT, `name` TEXT, `summary` TEXT, `authorid` INTEGER, FOREIGN KEY(`authorid`) REFERENCES `authors`(`id`))");
        $connection->commit();

        parent::setUp();
    }

    /**
     * (non-PHPdoc)
     *
     * @see PHPUnit_Extensions_Database_TestCase::tearDown()
     */
    protected function tearDown()
    {
        $connection = $this->getConnection()->getConnection();
        $connection->beginTransaction();
        $connection->exec("DROP TABLE books");
        $connection->exec("DROP TABLE authors");
        $connection->commit();

        parent::tearDown();
    }

    public function testRelations()
    {
        $author = new Author();
        $author->setName('Steven Hawking');

        $book = new Book();
        $book->setName('A brief history of time')
            ->setSummary('From wikipedia: From the Big Bang to Black Holes is a 1988 popular-science book')
            ->setAuthor($author);

        $book->persist();

        // And we add another book but take the already persisted author

        $stevenHawking = Author::find(array("name" => "Steven Hawking"));

        $anotherBook = new Book();
        $anotherBook->setName("The Universe in a Nutshell")
            ->setSummary("From wikipedia: Is one of Stephen Hawking's books on theoretical physics.")
            ->setAuthor($stevenHawking);

        $anotherBook->persist();

        // Now check if everything is fine...

        $allHawkingBooks = Book::findAll(array("author.name" => "Steven Hawking"));

        $this->assertEquals(2, count($allHawkingBooks));

        foreach($allHawkingBooks as $hawkingBook) {
            $this->assertNotNull($hawkingBook);
            $this->assertNotNull($hawkingBook->getAuthor());
            $this->assertEquals("Steven Hawking", $hawkingBook->getAuthor()->getName());
        }
    }
}