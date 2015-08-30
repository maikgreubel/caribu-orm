<?php
namespace Nkey\Caribu\Type;

use \Generics\Util\Interpolator;

use Nkey\Caribu\Orm\Orm;
use Nkey\Caribu\Orm\OrmException;

/**
 * Abstract database type
 *
 * This class is part of Caribu package
 *
 * @author Maik Greubel <greubel@nkey.de>
 */
abstract class AbstractType implements IType
{
    /**
     * Include generics interpolation functionality
     */
    use Interpolator;

    /**
     * Interpolate a string
     *
     * @param string $string
     * @param array $context
     * @return string
     */
    protected function interp($string, $context)
    {
        return $this->interpolate($string, $context);
    }

    /**
     * Retrieve primary key column for given table
     *
     * @param Orm $orm The Orm instance
     * @param string $table The name of tablee
     * @param string $sql The sql query which retrieves the column name
     *
     * @throws OrmException
     *
     * @return string The column name
     */
    protected function getPrimaryColumnViaSql(Orm $orm, $table, $sql)
    {
        $name = null;
        try {
            $stmt = $orm->getConnection()->query($sql);
            $stmt->setFetchMode(\PDO::FETCH_ASSOC);
            $count = 0;
            while ($result = $stmt->fetch()) {
                $name = $result['column_name'];
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

    /**
     * Lock table or row via sql
     *
     * @param Orm $orm The Orm instance
     * @param string $table The table name
     * @param string $sql The sql to execute for locking
     *
     * @throws OrmException
     */
    protected function lockViaSql(Orm $orm, $table, $sql)
    {
        $connection = $orm->getConnection();

        try {
            if ($connection->exec($sql) === false) {
                throw new OrmException("Could not lock table {table}", array('table' => $table));
            }
        } catch (\PDOException $ex) {
            throw OrmException::fromPrevious($ex, "Could not lock table");
        }
    }
}
