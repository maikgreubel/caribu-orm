<?php
namespace Nkey\Caribu\Orm;

/**
 * Statement provider for Caribu Orm
 *
 * This class is part of Caribu package
 *
 * @author Maik Greubel <greubel@nkey.de>
 */
trait OrmStatement
{
    /**
     * Include entity analyzing related functionality
     */
    use OrmEntityAnalyzer;

    /**
     * Create a query for selection
     *
     * @param string $class The class for which the query will be created
     * @param array  $criteria Array of criterias in form of "property" => "value"
     * @param array  $columns The columns to retrieve
     * @param string $orderBy An order-by statement in form of "property ASC|DESC"
     * @param number $limit The maximum amount of results
     * @param number $startFrom The offset where to get results of
     *
     * @return string The query as sql statement
     *
     * @throws OrmException
     */
    private static function createQuery(
        $class,
        $tableName,
        array &$criteria,
        array $columns,
        $orderBy = '',
        $limit = 0,
        $startFrom = 0,
        $escapeSign = ""
    ) {
        $joins = self::getAnnotatedQuery($class, $tableName, $criteria, $columns, $escapeSign);

        $wheres = self::parseCriteria($criteria, $escapeSign);

        $limits = self::parseLimits($limit, $startFrom);

        if ($orderBy && !stristr($orderBy, 'ORDER BY ')) {
            $orderBy = sprintf("ORDER BY %s%s%s", $escapeSign, $orderBy, $escapeSign);
        }

        $query = sprintf(
            "SELECT %s FROM %s%s%s %s %s %s %s",
            implode(',', $columns),
            $escapeSign,
            $tableName,
            $escapeSign,
            $joins,
            $wheres,
            $orderBy,
            $limits
        );

        return $query;
    }

    /**
     * Create a insert or update statement
     *
     * @param string    $class              The class of entity
     * @param array     $pairs              The pairs of columns and its corresponding values
     * @param string    $primaryKeyCol      The name of column which represents the primary key
     * @param mixed     $primaryKeyValue    The primary key value
     *
     * @return string
     */
    private static function createUpdateStatement($class, $pairs, $primaryKeyCol, $primaryKeyValue, $escapeSign)
    {
        $tableName = self::getTableName($class);

        $query = sprintf("INSERT INTO %s%s%s ", $escapeSign, $tableName, $escapeSign);
        if ($primaryKeyValue) {
            $query = sprintf("UPDATE %s%s%s ", $escapeSign, $tableName, $escapeSign);
        }

        $query .= self::persistenceQueryParams($pairs, $primaryKeyCol, is_null($primaryKeyValue), $escapeSign);

        if ($primaryKeyValue) {
            $query .= sprintf(" WHERE %s%s%s = :%s", $escapeSign, $primaryKeyCol, $escapeSign, $primaryKeyCol);
        }

        return $query;
    }

    /**
     * Escape all parts of a criterion
     *
     * @param string $criterion The criterion pattern
     * @param string $escapeSign The escape sign
     */
    private static function escapeCriterion($criterion, $escapeSign)
    {
        $criterionEscaped = '';
        $criterionParts = explode('.', $criterion);

        foreach ($criterionParts as $part) {
            $criterionEscaped .= $criterionEscaped ? '.' : '';
            $criterionEscaped .= sprintf("%s%s%s", $escapeSign, $part, $escapeSign);
        }

        return $criterionEscaped;
    }

    /**
     * Parse criteria into where conditions
     *
     * @param array $criteria The criteria to parse
     * @return string The where conditions
     *
     * @throws OrmException
     */
    private static function parseCriteria(array &$criteria, $escapeSign)
    {
        $wheres = array();

        $criterias = array_keys($criteria);

        foreach ($criterias as $criterion) {
            $placeHolder = str_replace('.', '_', $criterion);
            $placeHolder = str_replace('OR ', 'OR_', $placeHolder);
            if (strtoupper(substr($criteria[$criterion], 0, 4)) == 'LIKE') {
                $wheres[] = sprintf("%s LIKE :%s", self::escapeCriterion($criterion, $escapeSign), $placeHolder);
            } elseif (strtoupper(substr($criteria[$criterion], 0, 7)) == 'BETWEEN') {
                $start = $end = null;
                sscanf(strtoupper($criteria[$criterion]), "BETWEEN %s AND %s", $start, $end);
                if (!$start || !$end) {
                    throw new OrmException("Invalid range for between");
                }
                $wheres[] = sprintf(
                    "%s BETWEEN %s AND %s",
                    self::escapeCriterion($criterion, $escapeSign),
                    $start,
                    $end
                );
                unset($criteria[$criterion]);
            } else {
                $wheres[] = sprintf("%s = :%s", self::escapeCriterion($criterion, $escapeSign), $placeHolder);
            }
        }

        return self::whereConditionsAsString($wheres);
    }

    /**
     * Loops over all where conditions and create a string of it
     *
     * @param array $wheres
     * @return string The where conditions as string
     */
    private static function whereConditionsAsString(array $wheres)
    {
        if (count($wheres)) {
            $conditions = "";
            foreach ($wheres as $where) {
                $and = "";
                if ($conditions) {
                    $and = substr($where, 0, 3) == 'OR ' ? " " : " AND ";
                }
                $conditions .= $and . $where;
            }
            $wheres = sprintf("WHERE %s", $conditions);
        } else {
            $wheres = '';
        }

        return $wheres;
    }

    /**
     * Prepare the limit and offset modifier
     *
     * @param int $limit
     * @param int $startFrom
     *
     * @return string The limit modifier or empty string
     */
    private static function parseLimits($limit = 0, $startFrom = 0)
    {
        $limits = "";
        if ($startFrom > 0) {
            $limits = sprintf("%d,", $startFrom);
        }
        if ($limit > 0) {
            $limits .= $limit;
        }

        if ($limits) {
            $limits = sprintf("LIMIT %s", $limits);
        }

        return $limits;
    }

    /**
     * Retrieve the persistence parameters via reflection
     *
     * @param array $pairs The pairs of column names => values
     *
     * @return string The prepared statement parameters for persistence
     *
     * @throws OrmException
     */
    private static function persistenceQueryParams($pairs, $primaryKeyCol, $insert = true, $escapeSign = "")
    {
        $query = "";

        $columns = array_keys($pairs);

        if ($insert) {
            $cols = "";
            $vals = "";
            foreach ($columns as $column) {
                $cols .= ($cols ? ',' : '');
                $cols .= sprintf("%s%s%s", $escapeSign, $column, $escapeSign);
                $vals .= ($vals ? ',' : '');
                $vals .= sprintf(':%s', $column);
            }
            $query = sprintf("(%s) VALUES (%s)", $cols, $vals);
        } else {
            foreach ($columns as $column) {
                if ($column == $primaryKeyCol) {
                    continue;
                }
                $query .= ($query ? ", " : "SET ");
                $query .= sprintf("%s%s%s = :%s", $escapeSign, $column, $escapeSign, $column);
            }
        }

        return $query;
    }

    /**
     * When criterion is a property but annotated column name differs, we take the column name
     *
     * @param string $className The entity class name
     * @param string $criterion The criterion
     */
    private static function getAnnotatedCriterion($className, $criterion)
    {
        $class = new \ReflectionClass($className);
        $simpleCriterion = self::getSimpleCriterionName($criterion);
        if (!$class->hasProperty($simpleCriterion)) {
            return $criterion;
        }

        $property = $class->getProperty($simpleCriterion);

        if (strcmp($criterion, $simpleCriterion) != 0) {
            return $criterion;
        }

        $column = self::getAnnotatedColumn($property->getDocComment());

        if (null !== $column) {
            $criterion = str_replace($simpleCriterion, $column, $criterion);
        }

        return $criterion;
    }

    /**
     * Get the annotated join query
     *
     * @param string $class The name of class to use as left class
     * @param string $table The name of table
     * @param array $criteria
     *            (by reference) List of criterias
     * @param array $columns
     *            (by reference) List of columns
     * @param string $escapeSign The character which escapes special literals
     *
     * @return string The join query sql statment
     *
     * @throws OrmException
     */
    private static function getAnnotatedQuery($class, $table, &$criteria, &$columns, $escapeSign)
    {
        $joinQuery = "";

        $rf = new \ReflectionClass($class);

        $replacedCriteria = array();

        // Example criterion: user.name => 'john'
        foreach (array_keys($criteria) as $criterion) {
            $replacedCriteria[self::getAnnotatedCriterion($class, $criterion)] = $criteria[$criterion];
            // from example criterionProperty will be 'name', criterion will now be 'user'
            if (strpos($criterion, '.') !== false) {
                list ($criterion) = explode('.', $criterion);
            }

            // class must have a property named by criterion
            $rfProperty = $rf->hasProperty($criterion) ? $rf->getProperty($criterion) : null;
            if ($rfProperty == null) {
                continue;
            }

            // check annotations
            $propertyClass = "";
            // search the type of property value
            if (null !== ($type = self::getAnnotatedType($rfProperty->getDocComment(), $rf->getNamespaceName()))) {
                if (!self::isPrimitive($type) && class_exists($type)) {
                    $propertyClass = $type;
                }
            }
            $inverseTable = $propertyClass ? self::getAnnotatedTableName($propertyClass, $criterion) : $criterion;

            // search the table mapping conditions
            if (null !== ($parameters = self::getAnnotatedMappedByParameters($rfProperty->getDocComment()))) {
                $mappedBy = self::parseMappedBy($parameters);

                $pkCol = self::getAnnotatedPrimaryKeyColumn($class);
                $inversePkCol = self::getAnnotatedPrimaryKeyColumn($propertyClass);

                $joinQuery = sprintf(
                    "JOIN %s%s%s ON %s%s%s.%s%s%s = %s%s%s.%s%s%s ",
                    $escapeSign,
                    $mappedBy['table'],
                    $escapeSign,
                    $escapeSign,
                    $mappedBy['table'],
                    $escapeSign,
                    $escapeSign,
                    $mappedBy['inverseColumn'],
                    $escapeSign,
                    $escapeSign,
                    $table,
                    $escapeSign,
                    $escapeSign,
                    $pkCol,
                    $escapeSign
                );
                $joinQuery .= sprintf(
                    "JOIN %s%s%s ON %s%s%s.%s%s%s = %s%s%s.%s%s%s",
                    $escapeSign,
                    $inverseTable,
                    $escapeSign,
                    $escapeSign,
                    $inverseTable,
                    $escapeSign,
                    $escapeSign,
                    $inversePkCol,
                    $escapeSign,
                    $escapeSign,
                    $mappedBy['table'],
                    $escapeSign,
                    $escapeSign,
                    $mappedBy['column'],
                    $escapeSign
                );

                $columns[] = sprintf(
                    "%s%s%s.%s%s%s AS '%s.%s'",
                    $escapeSign,
                    $inverseTable,
                    $escapeSign,
                    $escapeSign,
                    $inversePkCol,
                    $escapeSign,
                    $inverseTable,
                    $inversePkCol
                );
            } elseif ($propertyClass != "") {
                $inversePkCol = self::getAnnotatedPrimaryKeyColumn($propertyClass);
                $column = self::getAnnotatedColumnFromProperty($class, $rfProperty->getName());
                $joinQuery = sprintf(
                    "JOIN %s%s%s AS %s%s%s ON %s%s%s.%s%s%s = %s%s%s.%s%s%s",
                    $escapeSign,
                    $inverseTable,
                    $escapeSign,
                    $escapeSign,
                    $criterion,
                    $escapeSign,
                    $escapeSign,
                    $criterion,
                    $escapeSign,
                    $escapeSign,
                    $inversePkCol,
                    $escapeSign,
                    $escapeSign,
                    $table,
                    $escapeSign,
                    $escapeSign,
                    $column,
                    $escapeSign
                );
                $columns[] = sprintf(
                    "%s%s%s.%s%s%s AS '%s.%s'",
                    $escapeSign,
                    $criterion,
                    $escapeSign,
                    $escapeSign,
                    $inversePkCol,
                    $escapeSign,
                    $criterion,
                    $inversePkCol
                );
            }
        }
        $criteria = $replacedCriteria;

        return $joinQuery;
    }

    /**
     * Parse a complex crition into simple criterion
     *
     * @param string $criterion The full criterion pattern
     *
     * @return string The simple criterion name
     */
    private static function getSimpleCriterionName($criterion)
    {
        $criterion = str_ireplace('OR ', '', $criterion);
        if (strpos($criterion, '.')) {
            list($criterion) = explode('.', $criterion);
        }
        return $criterion;
    }
}
