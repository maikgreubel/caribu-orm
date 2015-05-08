<?php
namespace Nkey\Caribu\Type;

use Nkey\Caribu\Orm\Orm;
use Nkey\Caribu\Orm\OrmException;

/**
 * Interface description of database type
 *
 * This class is part of Caribu package
 *
 * @author Maik Greubel <greubel@nkey.de>
 */
interface IType
{

    /**
     * Retrieve the data source name for the type
     *
     * @return string
     */
    public function getDsn();

    /**
     * Retrieve the default port of this type
     *
     * @return int
     */
    public function getDefaultPort();

    /**
     * Retrieve the table column which presents the primary key
     *
     * @param string $table
     * @param Orm $orm
     *
     * @return string
     *
     * @throws OrmException
     */
    public function getPrimaryKeyColumn($table, Orm $orm);
}
