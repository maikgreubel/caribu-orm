<?php
namespace Nkey\Caribu\Tests\Model;

use Nkey\Caribu\Model\AbstractModel;

/**
 *
 * @author Maik Greubel <greubel@nkey.de>
 *
 * @table blog
 * @eager
 */
class InvalidReferenceModel extends AbstractModel
{
    /**
     * @id
     */
    private $id;

    /**
     * @var string
     * @column content
     */
    private $content;

    /**
     * This referenced class does not exist!
     *
     * @var Poster
     *
     * @mappedBy(table=blog_user_to_posts,column=userid,inverseColumn=postid)
     */
    private $poster;

    /**
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     *
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
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
     * @return Poster
     */
    public function getPoster()
    {
        return $this->poster;
    }

    /**
     *
     * @param
     *            $poster
     */
    public function setPoster($poster)
    {
        $this->poster = $poster;
        return $this;
    }
}