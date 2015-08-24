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
            'schema' => getenv('PG_TEST_DATABASE') === false ? 'testing' : getenv('PG_TEST_DATABASE'),
            'user' => getenv('PG_TEST_USER') === false ? 'testing' : getenv('PG_TEST_USER'),
            'password' => getenv('PG_TEST_PASSWORD') === false ? md5('testing') : getenv('PG_TEST_PASSWORD')
        );
    }
}
