<?php
namespace Nkey\Caribu\Type;

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
    use \Generics\Util\Interpolator;

    /**
     * Interpolate a string
     *
     * @param string $string
     *            The string to interpolate
     * @param array $context
     *            The context variables and values to replace
     *            
     * @return string The interpolated string
     */
    protected function interp(string $string, array $context): string
    {
        return $this->interpolate($string, $context);
    }

    /**
     * Retrieve primary key column for given table
     *
     * @param Orm $orm
     *            The Orm instance
     * @param string $table
     *            The name of tablee
     * @param string $query
     *            The sql query which retrieves the column name
     *            
     * @return string The column name
     *        
     * @throws \Nkey\Caribu\Orm\OrmException
     */
    protected function getPrimaryColumnViaSql(\Nkey\Caribu\Orm\Orm $orm, string $table, string $query): string
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
                $count ++;
            }
            $stmt->closeCursor();
            
            if ($count > 1) {
                throw new \Nkey\Caribu\Orm\OrmException("Table {table} contains more than one primary key! Please annotate!", array(
                    'table' => $table
                ));
            }
        } catch (\PDOException $exception) {
            throw \Nkey\Caribu\Orm\OrmException::fromPrevious($exception);
        }
        
        return $name;
    }

    /**
     * Change the locks on table or row via sql
     *
     * @param Orm $orm
     *            The Orm instance
     * @param string $table
     *            The table name
     * @param string $sql
     *            The sql to execute for changing the lock level
     *            
     * @throws \Nkey\Caribu\Orm\OrmException
     */
    protected function changeLockViaSql(\Nkey\Caribu\Orm\Orm $orm, string $table, string $sql)
    {
        $connection = $orm->getConnection();
        
        try {
            if ($connection->exec($sql) === false) {
                throw new \Nkey\Caribu\Orm\OrmException("Could not change lock type table {table}", array(
                    'table' => $table
                ));
            }
        } catch (\PDOException $exception) {
            throw \Nkey\Caribu\Orm\OrmException::fromPrevious($exception, "Could not change lock type of table");
        }
    }

    /**
     * Map the type result from statement into an orm type
     *
     * @param array $result            
     *
     * @return int The orm type mapped
     */
    abstract protected function mapType(array $result): int;

    /**
     * Retrieve query which is results a mapable type from database
     *
     * @return string The query
     */
    abstract protected function getTypeQuery(): string;

    /**
     * (non-PHPdoc)
     *
     * @see \Nkey\Caribu\Type\IType::getColumnType()
     */
    public function getColumnType(string $table, string $columnName, \Nkey\Caribu\Orm\Orm $orm): int
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
        } catch (\PDOException $exception) {
            if ($stmt) {
                $stmt->closeCursor();
            }
            throw \Nkey\Caribu\Orm\OrmException::fromPrevious($exception);
        }
        
        return $type;
    }

    /**
     * Handle empty result while query table column
     *
     * @param string $table
     *            The name of table
     * @param string $columnName
     *            The name of column
     * @param Orm $orm
     *            The orm instance
     * @param \PDOStatement $stmt
     *            The prepared statement
     * @param array $result
     *            The result either filled or empty
     *            
     * @throws \Nkey\Caribu\Orm\OrmException
     */
    protected function handleNoColumn(string $table, string $columnName, \Nkey\Caribu\Orm\Orm $orm, \PDOStatement $stmt, array $result)
    {
        if (false === $result) {
            $stmt->closeCursor();
            throw new \Nkey\Caribu\Orm\OrmException("No such column {column} in {schema}.{table}", array(
                'column' => $columnName,
                'schema' => $orm->getSchema(),
                'table' => $table
            ));
        }
    }
}
