<?php
namespace Nkey\Caribu\Tests;

use Nkey\Caribu\Orm\Orm;
use Nkey\Caribu\Orm\OrmException;

use \PHPUnit_Framework_TestCase;

class InvalidTypeTest extends PHPUnit_Framework_TestCase
{
    /**
     * @expectedException Nkey\Caribu\Orm\OrmException
     */
    public function testInvalidType()
    {
        Orm::passivate();

        $options = array(
            'type' => 'invalidtype',
        );

        Orm::configure($options);
        Orm::getInstance()->getConnection();
    }
}