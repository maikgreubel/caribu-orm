<?php
namespace Nkey\Caribu\Type;

use \Nkey\Caribu\Orm\OrmException;
use \Nkey\Caribu\Orm\Orm;
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

        $sql = $this->interp($query, array(
            'table' => $table,
            'schema' => $orm->getSchema()
        ));

        return $this->getPrimaryColumnViaSql($orm, $table, $sql);
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

        $this->lockViaSql($orm, $table, sprintf("LOCK TABLES `%s` %s", $table, $lock));
    }

    /**
     * (non-PHPdoc)
     * @see \Nkey\Caribu\Type\IType::unlock()
     */
    public function unlock($table, Orm $orm)
    {
        $connection = $orm->getConnection();
        try {
            if ($connection->exec("UNLOCK TABLES") === false) {
                throw new OrmException("Could not unlock table {table}", array('table' => $table));
            }
        } catch(\PDOException $ex) {
            throw OrmException::fromPrevious($ex, "Could not unlock table");
        }
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
     * @see \Nkey\Caribu\Type\IType::getColumnType()
     */
    public function getColumnType($table, $columnName, Orm $orm)
    {
        $query = "SELECT `DATA_TYPE` FROM `INFORMATION_SCHEMA`.`COLUMNS` WHERE `TABLE_SCHEMA` = '{schema}' " .
            "AND `TABLE_NAME` = '{table}' AND `COLUMN_NAME` = '{column}'";

        $sql = $this->interp($query, array(
            'table' => $table,
            'schema' => $orm->getSchema(),
            'column' => $columnName
        ));

        $stmt = null;
        try
        {
            $stmt = $orm->getConnection()->query($sql);
            $stmt->setFetchMode(\PDO::FETCH_ASSOC);

            $result = $stmt->fetch();
            if (!$result) {
                $stmt->closeCursor();
                throw new OrmException("No such column {column} in {schema}.{table}", array(
                    'column' => $columnName,
                    'schema' => $orm->getSchema(),
                    'table' => $table
                ));
            }

            switch (strtoupper($result['DATA_TYPE'])) {
                case 'CHAR':
                case 'VARCHAR':
                case 'TEXT':
                case 'TINYTEXT':
                case 'MEDIUMTEXT':
                case 'LONGTEXT':
                case 'ENUM':
                case 'SET':
                    $type = OrmDataType::STRING;
                    break;

                case 'BINARY':
                case 'VARBINARY':
                case 'TINYBLOB':
                case 'BLOB':
                case 'MEDIUMBLOB':
                case 'LONGBLOB':
                    $type = OrmDataType::BLOB;
                    break;

                case 'INTEGER':
                case 'INT':
                case 'SMALLINT':
                case 'TINYINT':
                case 'MEDIUMINT':
                case 'BIGINT':
                    $type = OrmDataType::INTEGER;
                    break;

                case 'DECIMAL':
                case 'NUMERIC':
                case 'FLOAT':
                case 'REAL':
                case 'FIXED':
                case 'DEC':
                case 'DOUBLE PRECISION':
                    $type = OrmDataType::DECIMAL;
                    break;

                case 'DATE':
                case 'DATETIME':
                case 'TIMESTAMP':
                    $type = OrmDataType::DATETIME;
                    break;

                default:
                    $type = OrmDataType::STRING;
            }

            $stmt->closeCursor();
        } catch (\PDOException $ex) {
            if ($stmt) {
                $stmt->closeCursor();
            }
            throw OrmException::fromPrevious($ex);
        }

        return $type;
    }

    /**
     * (non-PHPdoc)
     * @see \Nkey\Caribu\Type\IType::getSequenceNameForColumn()
     */
    public function getSequenceNameForColumn($table, $columnName, Orm $orm)
    {
        return null;
    }
}
