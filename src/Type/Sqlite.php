<?php
namespace Nkey\Caribu\Type;

/**
 * Concrete sqlite implementation of database type
 *
 * This class is part of Caribu package
 *
 * @author Maik Greubel <greubel@nkey.de>
 */
class Sqlite extends AbstractType
{

    /**
     * (non-PHPdoc)
     *
     * @see \Nkey\Caribu\Type\IType::getDsn()
     */
    public function getDsn(): string
    {
        return "sqlite:{file}";
    }

    /**
     * (non-PHPdoc)
     *
     * @see \Nkey\Caribu\Type\IType::getPrimaryKeyColumn()
     */
    public function getPrimaryKeyColumn(string $table, \Nkey\Caribu\Orm\Orm $orm): string
    {
        $query = "PRAGMA TABLE_INFO({table})";
        
        $sql = $this->interp($query, array(
            'table' => $table
        ));
        
        try {
            $stmt = $orm->getConnection()->query($sql);
            $stmt->setFetchMode(\PDO::FETCH_ASSOC);
            while ($result = $stmt->fetch()) {
                $name = '';
                foreach ($result as $identifier => $value) {
                    if ($identifier == 'name') {
                        $name = $value;
                    }
                    if ($identifier == 'pk' && $name) {
                        return $name;
                    }
                }
            }
            $stmt->closeCursor();
        } catch (\PDOException $exception) {
            throw \Nkey\Caribu\Orm\OrmException::fromPrevious($exception);
        }
        
        return null;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \Nkey\Caribu\Type\IType::getDefaultPort()
     */
    public function getDefaultPort(): int
    {
        return - 1;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \Nkey\Caribu\Type\IType::lock()
     */
    public function lock(string $table, int $lockType, \Nkey\Caribu\Orm\Orm $orm)
    {
        return;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \Nkey\Caribu\Type\IType::unlock()
     */
    public function unlock(string $table, \Nkey\Caribu\Orm\Orm $orm)
    {
        return;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \Nkey\Caribu\Type\IType::getEscapeSign()
     */
    public function getEscapeSign(): string
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
        $query = "PRAGMA TABLE_INFO({table})";
        
        return $query;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \Nkey\Caribu\Type\AbstractType::mapType()
     */
    protected function mapType(array $result): int
    {
        switch ($result['type']) {
            case 'INTEGER':
                return \Nkey\Caribu\Orm\OrmDataType::INTEGER;
            
            case 'REAL':
                return \Nkey\Caribu\Orm\OrmDataType::DECIMAL;
            
            case 'BLOB':
                return \Nkey\Caribu\Orm\OrmDataType::BLOB;
            
            case 'TEXT':
            default:
                return \Nkey\Caribu\Orm\OrmDataType::STRING;
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see \Nkey\Caribu\Type\IType::getColumnType()
     */
    public function getColumnType(string $table, string $columnName, \Nkey\Caribu\Orm\Orm $orm): int
    {
        $type = null;
        
        try {
            $stmt = $orm->getConnection()->query($this->interp($this->getTypeQuery(), array(
                'table' => $table
            )));
            $stmt->setFetchMode(\PDO::FETCH_ASSOC);
            while ($result = $stmt->fetch()) {
                if ($result['name'] == $columnName) {
                    $type = $this->mapType($result);
                    break;
                }
            }
            $stmt->closeCursor();
        } catch (\PDOException $exception) {
            throw \Nkey\Caribu\Orm\OrmException::fromPrevious($exception);
        }
        
        return $type;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \Nkey\Caribu\Type\IType::getSequenceNameForColumn()
     */
    public function getSequenceNameForColumn(string $table, string $columnName, \Nkey\Caribu\Orm\Orm $orm): string
    {
        return null;
    }
}
