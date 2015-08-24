<?php
namespace Nkey\Caribu\Tests;

use Nkey\Caribu\Tests\AbstractDatabaseTestCase;

/**
 * Postgresql test case abstraction
 *
 * @author Maik Greubel <greubel@nkey.de>
 *         This class is part of Caribu package
 */
abstract class PostgresAbstractDatabaseTestCase extends AbstractDatabaseTestCase
{
    public function __construct()
    {
        $this->options = array(
            'type' => 'postgres',
            'host' => 'localhost',
            'schema' => getenv('TEST_DATABASE') === false ? 'testing' : getenv('TEST_DATABASE'),
            'user' => getenv('TEST_USER') === false ? 'testing' : getenv('TEST_USER'),
            'password' => getenv('TEST_PASSWORD') === false ? md5('testing') : getenv('TEST_PASSWORD')
        );
    }
}
