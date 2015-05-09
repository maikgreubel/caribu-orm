<?php
namespace Nkey\Caribu\Tests\Model;

use Nkey\Caribu\Model\AbstractModel;

/**
 * Annotated user model
 *
 * This class is part of Caribu package
 *
 * @author Maik Greubel <greubel@nkey.de>
 *
 *         @table user
 *         @entity
 */
class BlogUser extends AbstractModel
{

    /**
     * @id
     * @column id
     *
     * @var int
     */
    private $userId;

    /**
     *
     * @var string
     */
    private $name;

    /**
     *
     * @var string
     */
    private $email;

    /**
     * The blog posts, the user owns
     *
     * @mappedBy(table=blog_user_to_posts,column=postid,inverseColumn=userid)
     *
     * @var BlogPost[]
     */
    private $posts;

    /**
     *
     * @return the int
     */
    public function getUserId()
    {
        return $this->userId;
    }

    /**
     *
     * @param
     *            $userId
     */
    public function setUserId($userId)
    {
        $this->userId = $userId;
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
    public function getEmail()
    {
        return $this->email;
    }

    /**
     *
     * @param
     *            $email
     */
    public function setEmail($email)
    {
        $this->email = $email;
        return $this;
    }

    /**
     *
     * @return the BlogPost[]
     */
    public function getPosts()
    {
        return $this->posts;
    }

    /**
     *
     * @param
     *            $posts
     */
    public function setPosts($posts)
    {
        $this->posts = $posts;
        return $this;
    }
}
