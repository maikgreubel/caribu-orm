<?php
namespace Nkey\Caribu\Orm;

/**
 * Datatype enumeration for Caribu Orm
 *
 * This class is part of Caribu package
 *
 * @author Maik Greubel <greubel@nkey.de>
 */
interface OrmDataType
{
    const INTEGER  = 1;

    const STRING   = 2;

    const DECIMAL  = 4;

    const BLOB     = 8;

    const DATETIME = 16;
}
