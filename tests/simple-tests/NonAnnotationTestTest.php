<?php
namespace Nkey\Caribu\Tests;

require_once dirname(__FILE__).'/../AbstractDatabaseTestCase.php';
require_once dirname(__FILE__).'/../Model/ValidNoteModel.php';

use Nkey\Caribu\Tests\Model\ValidNoteModel;

use Nkey\Caribu\Orm\Orm;

/**
 * Non-annotation test cases
 *
 * This class is part of Caribu package
 *
 * @author Maik Greubel <greubel@nkey.de>
 */
class NonAnnotationTest extends AbstractDatabaseTestCase
{
    public function __construct()
    {
    	parent::__construct();
    	
        $this->options = array(
            'type' => 'sqlite',
            'file' => ':memory:'
        );

        $this->flatDataSetFile = dirname(__FILE__).'/../_files/notes-seed.xml';
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
        $connection->exec("CREATE TABLE notes (id INTEGER PRIMARY KEY, content TEXT, created TEXT)");
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
        $connection->exec("DROP TABLE notes");
        $connection->commit();

        parent::tearDown();
    }

    /**
     * Test non annotated
     */
    public function testMissingPrimaryKey()
    {
        $entity = new ValidNoteModel();
        $entity->setContent("Will not fail");
        $entity->setCreated(time());
        $entity->persist();

        $this->assertFalse(is_null($entity->getNoteId()));
    }

    /**
     * Test a find using empty criteria
     */
    public function testEmptyFind()
    {
        $entities = ValidNoteModel::find(array());
        $this->assertEquals(2, count($entities));
    }

    /**
     * @expectedException Nkey\Caribu\Orm\OrmException
     * @expectedExceptionMessage Invalid range for between
     */
    public function testInvalidBetween()
    {
        ValidNoteModel::find(array("noteId" => "BETWEEN"));
    }

    /**
     * Test a given limit and start offset
     */
    public function testLimitAndStartOffset()
    {
        $entities = ValidNoteModel::find(array(), "", 1);
        $this->assertTrue($entities instanceof ValidNoteModel);

        $entities = ValidNoteModel::find(array(), "", 2);
        $this->assertEquals(2, count($entities));

        $entities = ValidNoteModel::find(array(), "", 3);
        $this->assertEquals(2, count($entities));

        $entities = ValidNoteModel::find(array(), "", 2, 1);
        $this->assertTrue($entities instanceof ValidNoteModel);

        $entities = ValidNoteModel::find(array(), "", 2, 2);
        $this->assertTrue(is_null($entities));
    }
}
