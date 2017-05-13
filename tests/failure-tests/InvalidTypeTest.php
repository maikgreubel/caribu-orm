<?php
namespace Nkey\Caribu\Tests;

use Nkey\Caribu\Orm\Orm;

class InvalidTypeTest extends \PHPUnit\Framework\TestCase
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