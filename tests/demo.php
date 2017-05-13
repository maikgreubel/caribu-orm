<?php
/**
 * Please execute "composer install" first, to generate the autoload.php
 */
require dirname(__FILE__) . '/../vendor/autoload.php';

/**
 * Required types
 */
use Nkey\Caribu\Model\AbstractModel;
use Nkey\Caribu\Orm\Orm;
use Nkey\Caribu\Orm\OrmException;

/**
 * First you have to create a new table in schema 'test' of your mysql server
 * using the following statement:
 *
 * CREATE TABLE `demo` (`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY, `content` TEXT, `published` INT);
 */

/**
 * @entity
 * @table demo
 */
class Demo extends AbstractModel
{

    /**
     * The primary key
     *
     * @id
     *
     * @var int
     */
    private $id;

    /**
     * The content value
     *
     * @column content
     *
     * @var string
     */
    private $demoContent;

    /**
     * Published on
     *
     * @column published
     *
     * @var int
     */
    private $publishedOn;

    /**
     * @return int
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
     * @return string
     */
    public function getDemoContent()
    {
        return $this->demoContent;
    }

    /**
     *
     * @param
     *            $demoContent
     */
    public function setDemoContent($demoContent)
    {
        $this->demoContent = $demoContent;
        return $this;
    }

    /**
     *
     * @return int
     */
    public function getPublishedOn()
    {
        return $this->publishedOn;
    }

    /**
     *
     * @param
     *            $publishedOn
     */
    public function setPublishedOn($publishedOn)
    {
        $this->publishedOn = $publishedOn;
        return $this;
    }
}

/**
 * ***********************************************************************
 */

try {
    /**
     * Configure the OR mapper
     */
    Orm::configure(array(
        'type' => 'mysql',
        'host' => 'localhost',
        'user' => 'test',
        'password' => 'test1234',
        'schema' => 'test'
    ));

    // Now some business:
    $demoEntity = new Demo();
    $demoEntity->setDemoContent("Not so much important content, but it needs to be persisted!");
    $demoEntity->setPublishedOn(time());
    $demoEntity->persist();

    // After persistence we can access now the generated id:
    printf("Your persisted entity has the ID %d", $demoEntity->getId());
} catch (OrmException $exception) {

    while($exception != null) {
        echo $exception->getMessage() . "\n";
        echo $exception->getTraceAsString() . "\n";
        $exception = $exception->getPrevious();
    }
}
