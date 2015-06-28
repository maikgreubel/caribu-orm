<?php
namespace Nkey\Caribu\Tests\Model;

use Nkey\Caribu\Model\AbstractModel;

/**
 * Blog post model
 *
 * This class is part of Caribu package
 *
 * @author Maik Greubel <greubel@nkey.de>
 *
 * @table blog
 * @entity
 * @cascade
 * @eager
 */
class BlogPost extends AbstractModel
{
    /**
     * @id
     * @column id
     * @var int
     */
    private $postId;

    /**
     * The owner of the blog post
     *
     * @mappedBy(table=blog_user_to_posts,column=userid,inverseColumn=postid)
     *
     * @var \Nkey\Caribu\Tests\Model\BlogUser
     */
    private $user;

    /**
     *
     * @var string
     */
    private $content;

    /**
     *
     * @var string
     */
    private $created;

    /**
     *
     * @return the int
     */
    public function getPostId()
    {
        return $this->postId;
    }

    /**
     *
     * @param int $postId
     */
    public function setPostId($postId)
    {
        $this->postId = $postId;
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
     * @param string $content
     */
    public function setContent($content)
    {
        $this->content = $content;
        return $this;
    }

    /**
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

    /**
     *
     * @return BlogUser
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     *
     * @param BlogUser $user
     */
    public function setUser($user)
    {
        $this->user = $user;
        return $this;
    }
}
