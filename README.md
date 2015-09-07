[![Build Status](https://travis-ci.org/maikgreubel/caribu-orm.png)](https://travis-ci.org/maikgreubel/caribu-orm)
[![Coverage Status](https://coveralls.io/repos/maikgreubel/caribu-orm/badge.svg?branch=master)](https://coveralls.io/r/maikgreubel/caribu-orm?branch=master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/maikgreubel/caribu-orm/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/maikgreubel/caribu-orm/?branch=master)
[![Dependency Status](https://www.versioneye.com/user/projects/55e2c672c6d8f2001d000335/badge.svg?style=flat)](https://www.versioneye.com/user/projects/55e2c672c6d8f2001d000335)
[![Codacy Badge](https://api.codacy.com/project/badge/409051ceae4f41dabb0cb79bf0f2d5e1)](https://www.codacy.com/app/greubel/caribu-orm)

# caribu

An annotation-based PHP Object Relational Mapper

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
    
    use \Nkey\Caribu\Orm\Orm;
    
    /* First configure the ORM */
    Orm::configure(array(
      'type' => 'sqlite',
      'file' => ':memory:'
    ));
    
    // Create sqlite database table for memory storage
    // Orm::getInstance()->getConnection()->exec("CREATE TABLE super_duper_content (id INTEGER PRIMARY KEY, content TEXT)");
    
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

See the [Wiki](https://github.com/maikgreubel/caribu-orm/wiki) for more information about the capabilities and usage.
