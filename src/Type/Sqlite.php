<?php
namespace Nkey\Caribu\Type;

use \Nkey\Caribu\Orm\OrmException;
use \Nkey\Caribu\Orm\Orm;
use Nkey\Caribu\Orm\OrmDataType;

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
     * @see \Nkey\Caribu\Type\IType::getDsn()
     */
    public function getDsn()
    {
        return "sqlite:{file}";
    }

    /**
     * (non-PHPdoc)
     * @see \Nkey\Caribu\Type\IType::getPrimaryKeyColumn()
     */
    public function getPrimaryKeyColumn($table, Orm $orm)
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
            throw OrmException::fromPrevious($exception);
        }

        return null;
    }

    /**
     * (non-PHPdoc)
     * @see \Nkey\Caribu\Type\IType::getDefaultPort()
     */
    public function getDefaultPort()
    {
        return null;
    }

    /**
     * (non-PHPdoc)
     * @see \Nkey\Caribu\Type\IType::lock()
     */
    public function lock($table, $lockType, Orm $orm)
    {
        return;
    }

    /**
     * (non-PHPdoc)
     * @see \Nkey\Caribu\Type\IType::unlock()
     */
    public function unlock($table, Orm $orm)
    {
        return;
    }

    /**
     * (non-PHPdoc)
     * @see \Nkey\Caribu\Type\IType::getEscapeSign()
     */
    public function getEscapeSign()
    {
        return "";
    }

    /**
     * (non-PHPdoc)
     * @see \Nkey\Caribu\Type\AbstractType::getTypeQuery()
     */
    protected function getTypeQuery()
    {
        $query = "PRAGMA TABLE_INFO({table})";

        return $query;
    }

    /**
     * (non-PHPdoc)
     * @see \Nkey\Caribu\Type\AbstractType::mapType()
     */
    protected function mapType($result)
    {
        switch ($result['type']) {
            case 'INTEGER':
                return OrmDataType::INTEGER;

            case 'REAL':
                return OrmDataType::DECIMAL;

            case 'BLOB':
                return OrmDataType::BLOB;

            case 'TEXT':
            default:
                return OrmDataType::STRING;
        }
    }

    /**
     * (non-PHPdoc)
     * @see \Nkey\Caribu\Type\IType::getColumnType()
     */
    public function getColumnType($table, $columnName, Orm $orm)
    {
        $type = null;

        try {
            $stmt = $orm->getConnection()->query(
                $this->interp($this->getTypeQuery(), array(
                    'table' => $table
                ))
            );
            $stmt->setFetchMode(\PDO::FETCH_ASSOC);
            while ($result = $stmt->fetch()) {
                if ($result['name'] == $columnName) {
                    $type = $this->mapType($result);
                    break;
                }
            }
            $stmt->closeCursor();
        } catch (\PDOException $exception) {
            throw OrmException::fromPrevious($exception);
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
