<?php
namespace Nkey\Caribu\Tests\Model;

use Nkey\Caribu\Model\AbstractModel;

/**
 * An invalid entity model
 *
 * This class is part of Caribu package
 *
 * @author Maik Greubel <greubel@nkey.de>
 *
 * @table users
 * @entity
 */
class InvalidModel extends AbstractModel
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
    private $username;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    public function getUsername()
    {
        return $this->username;
    }

    public function setUsername($username)
    {
        $this->username = $username;
        return $this;
    }
}
