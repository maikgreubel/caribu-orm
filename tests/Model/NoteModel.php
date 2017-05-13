<?php
namespace Nkey\Caribu\Tests\Model;

use Nkey\Caribu\Model\AbstractModel;

/**
 * A very basic entity model
 *
 * This class is part of Caribu package
 *
 * @author Maik Greubel <greubel@nkey.de>
 */
class NoteModel extends AbstractModel
{
    /**
     *
     * @var int
     */
    private $noteId;

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
     * @return int
     */
    public function getNoteId()
    {
        return $this->noteId;
    }

    /**
     *
     * @param
     *            $noteId
     */
    public function setNoteId($noteId)
    {
        $this->noteId = $noteId;
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
     * @param
     *            $content
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
    public function getCreated()
    {
        return $this->created;
    }

    /**
     *
     * @param
     *            $created
     */
    public function setCreated($created)
    {
        $this->created = $created;
        return $this;
    }


}