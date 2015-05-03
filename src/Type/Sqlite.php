<?php
namespace Nkey\Caribu\Type;

use Nkey\Caribu\Orm\OrmException;

use \PDO;
use \PDOException;
use Nkey\Caribu\Orm\Orm;

/**
 * Concrete sqlite implementation of database type
 *
 * This class is part of Caribu package
 *
 * @author Maik Greubel <greubel@nkey.de>
 */
class Sqlite extends AbstractType
{

    /*
     * (non-PHPdoc)
     * @see \Nkey\Caribu\Type\IType::getDsn()
     */
    public function getDsn()
    {
        return "sqlite:{file}";
    }

    /*
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
            $stmt->setFetchMode(PDO::FETCH_ASSOC);
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
        } catch (PDOException $ex) {
            throw OrmException::fromPrevious($ex);
        }

        return null;
    }
}