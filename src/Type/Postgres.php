<?php
namespace Nkey\Caribu\Type;

use \Nkey\Caribu\Orm\OrmException;
use \Nkey\Caribu\Orm\Orm;
use Nkey\Caribu\Orm\OrmDataType;

/**
 * Concrete postgresql implementation of database type
 *
 * This class is part of Caribu package
 *
 * @author Maik Greubel <greubel@nkey.de>
 */
class Postgres extends AbstractType
{
    /**
     * (non-PHPdoc)
     * @see \Nkey\Caribu\Type\IType::getDsn()
     */
    public function getDsn()
    {
        // From the docs:
        //return "pgsql:host={host};port={port};dbname={schema};user={user};password={password}";
        return "pgsql:host={host};port={port};dbname={schema}";
    }

    /**
     * (non-PHPdoc)
     * @see \Nkey\Caribu\Type\IType::getDefaultPort()
     */
    public function getDefaultPort()
    {
        return 5432;
    }

    /**
     * (non-PHPdoc)
     * @see \Nkey\Caribu\Type\IType::getPrimaryKeyColumn()
     */
    public function getPrimaryKeyColumn($table, Orm $orm)
    {
        $query = "select ccu.column_name as column_name from information_schema.constraint_column_usage ccu " .
            "inner join information_schema.table_constraints tc on ccu.constraint_name = tc.constraint_name " .
            "where tc.table_catalog = '{schema}' AND tc.table_name = '{table}'";

        return $this->getPrimaryColumnViaSql($orm, $table, $query);
    }

    /**
     * (non-PHPdoc)
     * @see \Nkey\Caribu\Type\IType::lock()
     */
    public function lock($table, $lockType, Orm $orm)
    {
        $mode = "ACCESS SHARE";
        if ($lockType == IType::LOCK_TYPE_WRITE) {
            $mode = "ROW EXCLUSIVE";
        }

        $lockStatement = sprintf(
            "LOCK TABLE %s%s%s IN %s MODE NOWAIT",
            $this->getEscapeSign(),
            $table,
            $this->getEscapeSign(),
            $mode
        );

        $this->changeLockViaSql($orm, $table, $lockStatement);
    }

    /**
     * (non-PHPdoc)
     * @see \Nkey\Caribu\Type\IType::unlock()
     */
    public function unlock($table, Orm $orm)
    {
        // No unlock command; locks are released upon transaction end via commit or rollback
    }

    /**
     * (non-PHPdoc)
     * @see \Nkey\Caribu\Type\IType::getEscapeSign()
     */
    public function getEscapeSign()
    {
        return '"';
    }

    /**
     * (non-PHPdoc)
     * @see \Nkey\Caribu\Type\AbstractType::getTypeQuery()
     */
    protected function getTypeQuery()
    {
        $query = "select data_type from information_schema.columns where table_catalog = '{schema}' " .
            "and table_name = '{table}' and column_name = '{column}'";

        return $query;
    }

    /**
     * (non-PHPdoc)
     * @see \Nkey\Caribu\Type\AbstractType::mapType()
     */
    protected function mapType($result)
    {
        switch (strtoupper($result['data_type'])) {
            case 'SMALLINT':
            case 'INTEGER':
            case 'BIGINT':
                return OrmDataType::INTEGER;

            case 'NUMERIC':
            case 'MONEY':
                return OrmDataType::DECIMAL;

            case 'CHARACTER VARYING':
            case 'CHARACTER':
            case 'TEXT':
            case 'TIME':
                return OrmDataType::STRING;

            case 'BYTEA':
                return OrmDataType::BLOB;

            case 'TIMESTAMP':
            case 'DATE':
            case 'TIMESTAMP WITHOUT TIME ZONE':
                return OrmDataType::DATETIME;

            default:
                return OrmDataType::STRING;
        }
    }

    /**
     * (non-PHPdoc)
     * @see \Nkey\Caribu\Type\IType::getSequenceNameForColumn()
     */
    public function getSequenceNameForColumn($table, $columnName, Orm $orm)
    {
        $sequenceName = null;

        $query = "select column_default from information_schema.columns where table_catalog = '{schema}' " .
            "AND table_name = '{table}' and column_name = '{column}'";

        $sql = $this->interp($query, array(
            'schema' => $orm->getSchema(),
            'table' => $table,
            'column' => $columnName
        ));

        $stmt = null;

        try {
            $stmt = $orm->getConnection()->query($sql);
            $stmt->setFetchMode(\PDO::FETCH_ASSOC);

            $result = $stmt->fetch();

            $this->handleNoColumn($table, $columnName, $orm, $stmt, $result);

            $matches = array();
            if (preg_match("/nextval\('([^']+)'.*\)/", $result['column_default'], $matches)) {
                $sequenceName = $matches[1];
            }
            $stmt->closeCursor();
        } catch (\PDOException $ex) {
            if ($stmt) {
                $stmt->closeCursor();
            }
            throw OrmException::fromPrevious($ex);
        }

        return $sequenceName;
    }
}
