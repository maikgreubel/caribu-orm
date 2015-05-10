# caribu

An PHP Object Relational Mapper

This project aims to be an object relational mapper easy to use. The main target is to support developers writing only simple attribute containing classes and store it as entities into a database.

It has support for annotations so it is very flexible.

Here is a very basic example about the usage (for sqlite):

  ```sql
    CREATE TABLE super_duper_content (id INTEGER PRIMARY KEY, content TEXT);
  ```

  ```php
    /* SuperDuperEntity.php */
    
    /**
     * @table super_duper_content
     */
    class SuperDuperEntity extends AbstractModel
    {
      /**
       * @column id
       */
      private $sid;
      
      private $content;
      
      public function setSid($sid)
      {
        $this->sid = $sid;
      }
      
      public function getSid()
      {
        return $this->sid;
      }
      
      public function setContent($content)
      {
        $this->content = $content;
      }
      
      public function getContent()
      {
        return $this->content;
      }
    }
  ```
  
  ```php
    /* write-data-example.php */
    
    /* First configure the ORM */
    Nkey\Caribu\Orm\Orm::configure(array(
      'type' => 'sqlite',
      'file' => ':memory:'
    ));
    
    /* Now create a new entity and persist it to database */
    $entity = new SuperDuperEntity();
    $entity->setContent("Mega important content");
    $entity->persist();
  ```
 
  ```php
    /* read-data-example.php */
    
    /* First read the entity from database */
    $entity = SuperDuperEntity::find(array('content' => "Mega important content"));
    
    /* Display the content */
    echo $entity->getContent();
  ```
  
No need to write infrastructure and boilerblate code by yourself. Let the Orm do the hard work for you.

Caribu provides a convention-over-configuration behaviour by supporting annotations.

See the [[Wiki|https://github.com/maikgreubel/caribu-orm/wiki]] for more information about the capabilities and usage.
