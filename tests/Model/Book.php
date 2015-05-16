<?php
namespace Nkey\Caribu\Tests\Model;

use \Nkey\Caribu\Model\AbstractModel;
use \Nkey\Caribu\Tests\Model\Author;

/**
 * @table books
 * @entity
 * @cascade
 */
class Book extends AbstractModel
{
    /**
     * @id
     * @column id
     * @var int
     */
    private $id;

    /**
     * @column name
     * @var string
     */
    private $name;

    /**
     * @column summary;
     * @var string
     */
    private $summary;

    /**
     * @column authorid
     * @var Author
     */
    private $author;

    /**
     *
     * @return the int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     *
     * @param
     *            $id
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     *
     * @return the string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     *
     * @param
     *            $name
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     *
     * @return the string
     */
    public function getSummary()
    {
        return $this->summary;
    }

    /**
     *
     * @param
     *            $summary
     */
    public function setSummary($summary)
    {
        $this->summary = $summary;
        return $this;
    }

    /**
     *
     * @return the Author
     */
    public function getAuthor()
    {
        return $this->author;
    }

    /**
     *
     * @param Author $author
     */
    public function setAuthor(Author $author)
    {
        $this->author = $author;
        return $this;
    }
}