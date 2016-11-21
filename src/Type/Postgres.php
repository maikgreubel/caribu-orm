<?php
namespace Nkey\Caribu\Type;

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
     *
     * @see \Nkey\Caribu\Type\IType::getDsn()
     */
    public function getDsn(): string
    {
        // From the docs:
        // return "pgsql:host={host};port={port};dbname={schema};user={user};password={password}";
        return "pgsql:host={host};port={port};dbname={schema}";
    }

    /**
     * (non-PHPdoc)
     *
     * @see \Nkey\Caribu\Type\IType::getDefaultPort()
     */
    public function getDefaultPort(): int
    {
        return 5432;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \Nkey\Caribu\Type\IType::getPrimaryKeyColumn()
     */
    public function getPrimaryKeyColumn(string $table, \Nkey\Caribu\Orm\Orm $orm): string
    {
        $query = "select ccu.column_name as column_name from information_schema.constraint_column_usage ccu " . "inner join information_schema.table_constraints tc on ccu.constraint_name = tc.constraint_name " . "where tc.table_catalog = '{schema}' AND tc.table_name = '{table}'";
        
        return $this->getPrimaryColumnViaSql($orm, $table, $query);
    }

    /**
     * (non-PHPdoc)
     *
     * @see \Nkey\Caribu\Type\IType::lock()
     */
    public function lock(string $table, int $lockType, \Nkey\Caribu\Orm\Orm $orm)
    {
        $mode = "ACCESS SHARE";
        if ($lockType == IType::LOCK_TYPE_WRITE) {
            $mode = "ROW EXCLUSIVE";
        }
        
        $lockStatement = sprintf("LOCK TABLE %s%s%s IN %s MODE NOWAIT", $this->getEscapeSign(), $table, $this->getEscapeSign(), $mode);
        
        $this->changeLockViaSql($orm, $table, $lockStatement);
    }

    /**
     * (non-PHPdoc)
     *
     * @see \Nkey\Caribu\Type\IType::unlock()
     */
    public function unlock(string $table, \Nkey\Caribu\Orm\Orm $orm)
    {
        // No unlock command; locks are released upon transaction end via commit or rollback
    }

    /**
     * (non-PHPdoc)
     *
     * @see \Nkey\Caribu\Type\IType::getEscapeSign()
     */
    public function getEscapeSign(): string
    {
        return '"';
    }

    /**
     * (non-PHPdoc)
     *
     * @see \Nkey\Caribu\Type\AbstractType::getTypeQuery()
     */
    protected function getTypeQuery(): string
    {
        $query = "select data_type from information_schema.columns where table_catalog = '{schema}' " . "and table_name = '{table}' and column_name = '{column}'";
        
        return $query;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \Nkey\Caribu\Type\AbstractType::mapType()
     */
    protected function mapType(array $result): int
    {
        switch (strtoupper($result['data_type'])) {
            case 'SMALLINT':
            case 'INTEGER':
            case 'BIGINT':
                return \Nkey\Caribu\Orm\OrmDataType::INTEGER;
            
            case 'NUMERIC':
            case 'MONEY':
                return \Nkey\Caribu\Orm\OrmDataType::DECIMAL;
            
            case 'CHARACTER VARYING':
            case 'CHARACTER':
            case 'TEXT':
            case 'TIME':
                return \Nkey\Caribu\Orm\OrmDataType::STRING;
            
            case 'BYTEA':
                return \Nkey\Caribu\Orm\OrmDataType::BLOB;
            
            case 'TIMESTAMP':
            case 'DATE':
            case 'TIMESTAMP WITHOUT TIME ZONE':
                return \Nkey\Caribu\Orm\OrmDataType::DATETIME;
            
            default:
                return \Nkey\Caribu\Orm\OrmDataType::STRING;
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see \Nkey\Caribu\Type\IType::getSequenceNameForColumn()
     */
    public function getSequenceNameForColumn(string $table, string $columnName, \Nkey\Caribu\Orm\Orm $orm): string
    {
        $sequenceName = null;
        
        $query = "select column_default from information_schema.columns where table_catalog = '{schema}' " . "AND table_name = '{table}' and column_name = '{column}'";
        
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
        } catch (\PDOException $exception) {
            if ($stmt) {
                $stmt->closeCursor();
            }
            throw \Nkey\Caribu\Orm\OrmException::fromPrevious($exception);
        }
        
        return $sequenceName;
    }
}
