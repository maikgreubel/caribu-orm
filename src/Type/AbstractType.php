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
     * @param string $query The sql query which retrieves the column name
     *
     * @throws OrmException
     *
     * @return string The column name
     */
    protected function getPrimaryColumnViaSql(Orm $orm, $table, $query)
    {
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
     * Change the locks on table or row via sql
     *
     * @param Orm $orm The Orm instance
     * @param string $table The table name
     * @param string $sql The sql to execute for changing the lock level
     *
     * @throws OrmException
     */
    protected function changeLockViaSql(Orm $orm, $table, $sql)
    {
        $connection = $orm->getConnection();

        try {
            if ($connection->exec($sql) === false) {
                throw new OrmException("Could not change lock type table {table}", array('table' => $table));
            }
        } catch (\PDOException $ex) {
            throw OrmException::fromPrevious($ex, "Could not change lock type of table");
        }
    }

    /**
     * Map the type result from statement into an orm type
     *
     * @param array $result
     *
     * @return integer The orm type mapped
     */
    abstract protected function mapType($result);

    /**
     * Retrieve query which is results a mapable type from database
     *
     * @return string The query
     */
    abstract protected function getTypeQuery();

    /**
     * (non-PHPdoc)
     * @see \Nkey\Caribu\Type\IType::getColumnType()
     */
    public function getColumnType($table, $columnName, Orm $orm)
    {
        $sql = $this->interp($this->getTypeQuery(), array(
            'table' => $table,
            'schema' => $orm->getSchema(),
            'column' => $columnName
        ));

        $stmt = null;
        try {
            $stmt = $orm->getConnection()->query($sql);
            $stmt->setFetchMode(\PDO::FETCH_ASSOC);

            $result = $stmt->fetch();

            $this->handleNoColumn($table, $columnName, $orm, $stmt, $result);

            $type = $this->mapType($result);

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
     * Handle empty result while query table column
     *
     * @param string $table The name of table
     * @param string $columnName The name of column
     * @param Orm $orm The orm instance
     * @param \PDOStatement $stmt The prepared statement
     * @param array $result The result either filled or empty
     *
     * @throws OrmException
     */
    protected function handleNoColumn($table, $columnName, $orm, $stmt, $result)
    {
        if (!$result) {
            $stmt->closeCursor();
            throw new OrmException("No such column {column} in {schema}.{table}", array(
                'column' => $columnName,
                'schema' => $orm->getSchema(),
                'table' => $table
            ));
        }
    }
}
