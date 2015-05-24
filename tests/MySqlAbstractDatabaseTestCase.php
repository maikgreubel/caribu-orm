<?php
namespace Nkey\Caribu\Tests;

use Nkey\Caribu\Tests\AbstractDatabaseTestCase;

/**
 * MySql test case abstraction
 *
 * @author Maik Greubel <greubel@nkey.de>
 *         This class is part of Caribu package
 */
abstract class MySqlAbstractDatabaseTestCase extends AbstractDatabaseTestCase
{
    public function __construct()
    {
        $this->options = array(
            'type' => 'mysql',
            'host' => 'localhost',
            'schema' => getenv('TEST_DATABASE') === false ? 'test' : getenv('TEST_DATABASE'),
            'user' => getenv('TEST_USER') === false ? 'test' : getenv('TEST_USER'),
            'password' => getenv('TEST_PASSWORD') === false ? '' : getenv('TEST_PASSWORD')
        );
    }
}
