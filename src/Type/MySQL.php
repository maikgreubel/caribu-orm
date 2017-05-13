<?php
namespace Nkey\Caribu\Type;

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
     *
     * @see \Nkey\Caribu\Type\IType::getDsn()
     */
    public function getDsn(): string
    {
        return "mysql:host={host};port={port};dbname={schema}";
    }

    /**
     * (non-PHPdoc)
     *
     * @see \Nkey\Caribu\Type\IType::getDefaultPort()
     */
    public function getDefaultPort(): int
    {
        return 3306;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \Nkey\Caribu\Type\IType::getPrimaryKeyColumn()
     */
    public function getPrimaryKeyColumn(string $table, \Nkey\Caribu\Orm\Orm $orm): string
    {
        $query = "SELECT `COLUMN_NAME` as column_name FROM `information_schema`.`columns` " . //
"WHERE `TABLE_NAME` = '{table}' AND `TABLE_SCHEMA` = '{schema}' AND `COLUMN_KEY` = 'PRI'";
        
        return $this->getPrimaryColumnViaSql($orm, $table, $query);
    }

    /**
     * (non-PHPdoc)
     *
     * @see \Nkey\Caribu\Type\IType::lock()
     */
    public function lock(string $table, int $lockType, \Nkey\Caribu\Orm\Orm $orm)
    {
        $lock = "READ";
        if ($lockType == IType::LOCK_TYPE_WRITE) {
            $lock = "WRITE";
        }
        
        $this->changeLockViaSql($orm, $table, sprintf("LOCK TABLES `%s` %s", $table, $lock));
    }

    /**
     * (non-PHPdoc)
     *
     * @see \Nkey\Caribu\Type\IType::unlock()
     */
    public function unlock(string $table, \Nkey\Caribu\Orm\Orm $orm)
    {
        $this->changeLockViaSql($orm, $table, "UNLOCK TABLES");
    }

    /**
     * (non-PHPdoc)
     *
     * @see \Nkey\Caribu\Type\IType::getEscapeSign()
     */
    public function getEscapeSign(): string
    {
        return "`";
    }

    /**
     * (non-PHPdoc)
     *
     * @see \Nkey\Caribu\Type\IType::getSequenceNameForColumn()
     */
    public function getSequenceNameForColumn(string $table, string $columnName, \Nkey\Caribu\Orm\Orm $orm): string
    {
        return "";
    }

    /**
     * (non-PHPdoc)
     *
     * @see \Nkey\Caribu\Type\AbstractType::getTypeQuery()
     */
    protected function getTypeQuery(): string
    {
        $query = "SELECT `DATA_TYPE` FROM `INFORMATION_SCHEMA`.`COLUMNS` WHERE `TABLE_SCHEMA` = '{schema}' " . "AND `TABLE_NAME` = '{table}' AND `COLUMN_NAME` = '{column}'";
        
        return $query;
    }

    /**
     * Checks whether given type is a string type
     *
     * @param string $type            
     *
     * @return bool true in case of type is string type, false otherwise
     */
    private function isStringType(string $type): bool
    {
        return $type === 'CHAR' || $type === 'VARCHAR' || $type === 'TEXT' || $type === 'TINYTEXT' || $type === 'MEDIUMTEXT' || $type === 'LONGTEXT' || $type === 'ENUM' || $type === 'SET';
    }

    /**
     * Checks whether given type is a binary type
     *
     * @param string $type            
     *
     * @return bool true in case of type is binary type, false otherwise
     */
    private function isBinaryType(string $type): bool
    {
        return $type === 'BINARY' || $type === 'VABINARY' || $type === 'TINYBLOB' || $type === 'BLOB' || $type === 'MEDIUMBLOB' || $type === 'LONGBLOB';
    }

    /**
     * Checks whether given type is a integer type
     *
     * @param string $type            
     *
     * @return bool true in case of type is integer type, false otherwise
     */
    private function isIntegerType(string $type): bool
    {
        return $type === 'INTEGER' || $type === 'INT' || $type === 'SMALLINT' || $type === 'TINYINT' || $type === 'MEDIUMINT' || $type === 'BIGINT';
    }

    /**
     * Checks whether given type is a decimal type
     *
     * @param string $type            
     *
     * @return bool true in case of type is decimal type, false otherwise
     */
    private function isDecimalType(string $type): bool
    {
        return $type === 'DECIMAL' || $type === 'NUMERIC' || $type === 'FLOAT' || $type === 'REAL' || $type === 'FIXED' || $type === 'DEC' || $type === 'DOUBLE PRECISION';
    }

    /**
     * Checks whether given type is a datetime type
     *
     * @param string $type            
     *
     * @return bool true in case of type is datetime type, false otherwise
     */
    private function isDateTimeType(string $type): bool
    {
        return $type === 'DATE' || $type === 'DATETIME' || $type === 'TIMESTAMP';
    }

    /**
     * (non-PHPdoc)
     *
     * @see \Nkey\Caribu\Type\AbstractType::mapType()
     */
    protected function mapType(array $result): int
    {
        $type = strtoupper($result['DATA_TYPE']);
        
        if ($this->isStringType($type)) {
            return \Nkey\Caribu\Orm\OrmDataType::STRING;
        }
        
        if ($this->isBinaryType($type)) {
            return \Nkey\Caribu\Orm\OrmDataType::BLOB;
        }
        
        if ($this->isIntegerType($type)) {
            return \Nkey\Caribu\Orm\OrmDataType::INTEGER;
        }
        
        if ($this->isDecimalType($type)) {
            return \Nkey\Caribu\Orm\OrmDataType::DECIMAL;
        }
        
        if ($this->isDateTimeType($type)) {
            return \Nkey\Caribu\Orm\OrmDataType::DATETIME;
        }
        
        return \Nkey\Caribu\Orm\OrmDataType::STRING;
    }
}
