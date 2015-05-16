<?php
namespace Nkey\Caribu\Tests\Model;

use Nkey\Caribu\Model\AbstractModel;

/**
 * @table authors
 * @entity
 */
class Author extends AbstractModel
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
}
