<?php
namespace Nkey\Caribu\Tests\Model;

use Nkey\Caribu\Model\AbstractModel;

/**
 * Annotated guestbook entity model
 *
 * This class is part of Caribu package
 *
 * @author Maik Greubel <greubel@nkey.de>
 *
 * @table guestbook
 */
class AnnotatedGuestBookModel extends AbstractModel
{
    /**
     * @var int
     * @id
     * @column id
     */
    private $gid;

    /**
     *
     * @var string
     */
    private $content;

    /**
     *
     * @var string
     */
    private $user;

    /**
     *
     * @var \DateTime
     */
    private $created;

    /**
     *
     * @return int
     */
    public function getGid()
    {
        return $this->gid;
    }

    /**
     * @param int $id
     */
    public function setGid($id)
    {
        $this->gid = $id;
        return $this;
    }

    /**
     *
     * @return string
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     *
     * @param string $content
     */
    public function setContent($content)
    {
        $this->content = $content;
        return $this;
    }

    /**
     *
     * @return string
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     *
     * @param string $user
     */
    public function setUser($user)
    {
        $this->user = $user;
        return $this;
    }

    /**
     *
     * @return string
     */
    public function getCreated()
    {
        return $this->created;
    }

    /**
     *
     * @param string $created
     */
    public function setCreated($created)
    {
        $this->created = $created;
        return $this;
    }
}