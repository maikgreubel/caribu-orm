<?php
namespace Nkey\Caribu\Tests;

require_once dirname(__FILE__).'/../AbstractDatabaseTestCase.php';
require_once dirname(__FILE__).'/../Model/MockedModel.php';

use Nkey\Caribu\Tests\Model\MockedModel;

use Nkey\Caribu\Tests\AbstractDatabaseTestCase;
use Nkey\Caribu\Orm\Orm;

/**
 * Complex test cases (sqlite is used)
 *
 * This class is part of Caribu package
 *
 * @author Maik Greubel <greubel@nkey.de>
 */
class InvalidConnectionSettingsTest extends AbstractDatabaseTestCase
{
    public function __construct()
    {
        $this->options = array(
            'type' => 'mysql',
            'host' => 'localhost',
            'port' => 1234,
            'schema' => getenv('TEST_DATABASE') === false ? 'test' : getenv('TEST_DATABASE'),
            'user' => getenv('TEST_USER') === false ? 'test' : getenv('TEST_USER'),
            'password' => getenv('TEST_PASSWORD') === false ? '' : getenv('TEST_PASSWORD')
        );
    }

    /**
     * @expectedException Nkey\Caribu\Orm\OrmException
     */
    public function testInvalidConnection()
    {
        Orm::passivate();
        parent::setUp();
        MockedModel::find(array());
    }
}
