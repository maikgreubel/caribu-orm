<?php
namespace Nkey\Caribu\Type;

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
     * To lock tables in read mode
     * @final
     */
    const LOCK_TYPE_READ = 1;

    /**
     * To lock tables in write mode
     * @final
     */
    const LOCK_TYPE_WRITE = 2;

    /**
     * Retrieve the data source name for the type
     *
     * @return string The data source name
     */
    public function getDsn();

    /**
     * Retrieve the default port of this type
     *
     * @return int The default port
     */
    public function getDefaultPort();

    /**
     * Retrieve the table column which presents the primary key
     *
     * @param string $table
     * @param \Nkey\Caribu\Orm\Orm $orm
     *
     * @return string|null The name of the column
     *
     * @throws \Nkey\Caribu\Orm\OrmException
     */
    public function getPrimaryKeyColumn($table, \Nkey\Caribu\Orm\Orm $orm);

    /**
     * Lock a specific table
     * @param string $table The table to lock
     * @param int $lockType The type of lock to acquire
     * @param \Nkey\Caribu\Orm\Orm $orm The orm instance
     * @throws \Nkey\Caribu\Orm\OrmException
     */
    public function lock($table, $lockType, \Nkey\Caribu\Orm\Orm $orm);

    /**
     * Unlock a specific table
     * @param string $table The table to unlocks
     * @param \Nkey\Caribu\Orm\Orm $orm The orm instance
     * @throws \Nkey\Caribu\Orm\OrmException
     */
    public function unlock($table, \Nkey\Caribu\Orm\Orm $orm);

    /**
     * Retrieve the character for escaping special and reserved words
     *
     * @return string The escape character
     */
    public function getEscapeSign();

    /**
     * Retrieve the datatype of column
     *
     * @param string $table The name of table where the column exists
     * @param string $columnName The name of column
     * @param \Nkey\Caribu\Orm\Orm $orm The orm instance
     *
     * @return int The datatype of column
     * @throws \Nkey\Caribu\Orm\OrmException
     */
    public function getColumnType($table, $columnName, \Nkey\Caribu\Orm\Orm $orm);

    /**
     * Retrieve the sequence name for a given column
     *
     * @param string $table The name of table to get the sequence name of
     * @param string $columnName The name of column to get the sequence name of
     * @param \Nkey\Caribu\Orm\Orm $orm The orm instance
     *
     * @return string The name of the sequence
     *
     * @throws \Nkey\Caribu\Orm\OrmException
     */
    public function getSequenceNameForColumn($table, $columnName, \Nkey\Caribu\Orm\Orm $orm);
}
