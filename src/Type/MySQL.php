<?php
namespace Nkey\Caribu\Type;

use \Nkey\Caribu\Orm\OrmException;
use \Nkey\Caribu\Orm\Orm;

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
        $query = "SELECT `COLUMN_NAME` FROM `information_schema`.`columns` " . //
            "WHERE `TABLE_NAME` = '{table}' AND `TABLE_SCHEMA` = '{schema}' AND `COLUMN_KEY` = 'PRI'";

        $sql = $this->interp($query, array(
            'table' => $table,
            'schema' => $orm->getSchema()
        ));

        $name = null;
        try {
            $stmt = $orm->getConnection()->query($sql);
            $stmt->setFetchMode(\PDO::FETCH_ASSOC);
            $count = 0;
            while ($result = $stmt->fetch()) {
                $name = $result['COLUMN_NAME'];
                $count++;
            }
            $stmt->closeCursor();

            if ($count > 1) {
                throw new OrmException("Table {table} contains more than one primary key! Please annotate!", array(
                    'table' => $table
                ));
            }
        } catch (\PDOException $ex) {
            throw OrmException::fromPrevious($ex);
        }

        return $name;

    }
}
