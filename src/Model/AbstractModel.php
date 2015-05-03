<?php
namespace Nkey\Caribu\Model;

use Nkey\Caribu\Orm\Orm;

/**
 * Abstract entity model
 *
 * This class is part of Caribu package
 *
 * @author Maik Greubel <greubel@nkey.de>
 */
abstract class AbstractModel extends Orm
{

    public function __construct()
    {}
}