<?php
namespace Nkey\Caribu\Type;

use Nkey\Caribu\Orm\Orm;
use Nkey\Caribu\Orm\OrmDataType;

/**
 * Concrete mysql implementation of database type
 *
 * This class is part of Caribu package
 *
 * @author Maik Greubel <greubel@nkey.de>
 */
class MySQL extends AbstractType
{
    /**
     * (non-PHPdoc)
     * @see \Nkey\Caribu\Type\IType::getDsn()
     */
    public function getDsn()
    {
        return "mysql:host={host};port={port};dbname={schema}";
    }

    /**
     * (non-PHPdoc)
     * @see \Nkey\Caribu\Type\IType::getDefaultPort()
     */
    public function getDefaultPort()
    {
        return 3306;
    }

    /**
     * (non-PHPdoc)
     * @see \Nkey\Caribu\Type\IType::getPrimaryKeyColumn()
     */
    public function getPrimaryKeyColumn($table, Orm $orm)
    {
        $query = "SELECT `COLUMN_NAME` as column_name FROM `information_schema`.`columns` " . //
            "WHERE `TABLE_NAME` = '{table}' AND `TABLE_SCHEMA` = '{schema}' AND `COLUMN_KEY` = 'PRI'";

        return $this->getPrimaryColumnViaSql($orm, $table, $query);
    }

    /**
     * (non-PHPdoc)
     * @see \Nkey\Caribu\Type\IType::lock()
     */
    public function lock($table, $lockType, Orm $orm)
    {
        $lock = "READ";
        if($lockType == IType::LOCK_TYPE_WRITE) {
            $lock = "WRITE";
        }

        $this->changeLockViaSql($orm, $table, sprintf("LOCK TABLES `%s` %s", $table, $lock));
    }

    /**
     * (non-PHPdoc)
     * @see \Nkey\Caribu\Type\IType::unlock()
     */
    public function unlock($table, Orm $orm)
    {
        $this->changeLockViaSql($orm, $table, "UNLOCK TABLES");
    }

    /**
     * (non-PHPdoc)
     * @see \Nkey\Caribu\Type\IType::getEscapeSign()
     */
    public function getEscapeSign()
    {
        return "`";
    }

    /**
     * (non-PHPdoc)
     * @see \Nkey\Caribu\Type\IType::getSequenceNameForColumn()
     */
    public function getSequenceNameForColumn($table, $columnName, Orm $orm)
    {
        return null;
    }

    /**
     * (non-PHPdoc)
     * @see \Nkey\Caribu\Type\AbstractType::getTypeQuery()
     */
    protected function getTypeQuery(Orm $orm, $table, $columnName)
    {
        $query = "SELECT `DATA_TYPE` FROM `INFORMATION_SCHEMA`.`COLUMNS` WHERE `TABLE_SCHEMA` = '{schema}' " .
            "AND `TABLE_NAME` = '{table}' AND `COLUMN_NAME` = '{column}'";

        $sql = $this->interp($query, array(
            'table' => $table,
            'schema' => $orm->getSchema(),
            'column' => $columnName
        ));

        return $sql;
    }

    /**
     * (non-PHPdoc)
     * @see \Nkey\Caribu\Type\AbstractType::mapType()
     */
    protected function mapType($result)
    {
        switch (strtoupper($result['DATA_TYPE'])) {
            case 'CHAR':
            case 'VARCHAR':
            case 'TEXT':
            case 'TINYTEXT':
            case 'MEDIUMTEXT':
            case 'LONGTEXT':
            case 'ENUM':
            case 'SET':
                return OrmDataType::STRING;
                break;

            case 'BINARY':
            case 'VARBINARY':
            case 'TINYBLOB':
            case 'BLOB':
            case 'MEDIUMBLOB':
            case 'LONGBLOB':
                return OrmDataType::BLOB;
                break;

            case 'INTEGER':
            case 'INT':
            case 'SMALLINT':
            case 'TINYINT':
            case 'MEDIUMINT':
            case 'BIGINT':
                return OrmDataType::INTEGER;
                break;

            case 'DECIMAL':
            case 'NUMERIC':
            case 'FLOAT':
            case 'REAL':
            case 'FIXED':
            case 'DEC':
            case 'DOUBLE PRECISION':
                return OrmDataType::DECIMAL;
                break;

            case 'DATE':
            case 'DATETIME':
            case 'TIMESTAMP':
                return OrmDataType::DATETIME;
                break;

            default:
                return OrmDataType::STRING;
        }
    }
}
