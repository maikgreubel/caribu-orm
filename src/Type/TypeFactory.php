<?php
namespace Nkey\Caribu\Type;

use Nkey\Caribu\Orm\Orm;
use Nkey\Caribu\Orm\OrmException;

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
     *            Orm instance
     *            
     * @return IType
     *
     * @throws OrmException
     */
    public static function create(Orm $orm): IType
    {
        $type = $orm->getType();
        switch ($type) {
            case 'sqlite':
                return new Sqlite();
            
            case 'mysql':
                return new MySQL();
            
            case 'postgres':
                return new Postgres();
            
            default:
                throw new OrmException("No such type {type}", array(
                    'type' => $type
                ));
        }
    }
}
