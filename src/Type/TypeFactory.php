<?php
namespace Nkey\Caribu\Type;

use Nkey\Caribu\Orm\Orm;
use Nkey\Caribu\Orm\OrmException;

use Nkey\Caribu\Type\IType;

/**
 * Database type factory
 *
 * This class is part of Caribu package
 *
 * @author Maik Greubel <greubel@nkey.de>
 */
abstract class TypeFactory
{

    /**
     * Creata a database type
     *
     * @param Orm $orm
     * @throws OrmException
     * @return IType
     */
    public static function create(Orm $orm)
    {
        $type = $orm->getType();
        switch ($type) {
            case 'sqlite':
                return new Sqlite();
                break;

            default:
                throw new OrmException("No such type {type}", array(
                    'type' => $type
                ));
        }
    }
}
