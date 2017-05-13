<?php
namespace Nkey\Caribu\Orm;

/**
 * Datatype conversion provider for Caribu Orm
 *
 * This class is part of Caribu package
 *
 * @author Maik Greubel <greubel@nkey.de>
 */
trait OrmDataTypeConverter
{
    /**
     * Include some basic utility functions
     */
    use OrmUtil;

    /**
     * Convert a date string into DateTime object
     *
     * Accepted values are (in this order)
     *
     * - Unix timestamp
     * - Single ISO8601 date
     * - Simple ISO8601 date and time without timezone
     * - DateTime::W3C
     * - DateTime::ISO8601
     *
     * @param string $value            
     *
     * @return \DateTime
     */
    private static function convertDate(string $value): \DateTime
    {
    	$value = trim($value);
        try {
            $date = new \DateTime(sprintf("@%s", $value));
        } catch (\Exception $exception) {
            try {
                $date = \DateTime::createFromFormat("Y-m-d", $value);
                
                if (! $date) {
                    $date = \DateTime::createFromFormat("Y-m-d H:i:s", $value);
                }
                
                if (! $date) {
                    $date = \DateTime::createFromFormat(\DateTime::W3C, $value);
                }
                
                if (! $date) {
                    $date = \DateTime::createFromFormat(\DateTime::ISO8601, $value);
                }
                
                if (! $date) {
                	throw new OrmException("Could not parse string '".$value."' as date");
                }
            } catch (\Exception $exception) {
                throw OrmException::fromPrevious($exception);
            }
        }
        
        return $date;
    }

    /**
     * Convert arbitrary data into given type
     *
     * @param string $type
     *            The type to convert data into
     * @param mixed $value
     *            The value to convert
     * @throws OrmException
     *
     * @return mixed The converted data
     */
    private static function convertType(string $type, $value)
    {
        if (! $type) {
            return $value;
        }
        
        if ($value instanceof $type) {
            return $value;
        }
        
        switch ($type) {
            case 'bool':
            case 'boolean':
                return self::boolval($value);
            
            case 'int':
            case 'integer':
            case 'number':
                return intval($value);
            
            case 'float':
            case 'double':
            case 'real':
            case 'decimal':
                return doubleval($value);
            
            case 'string':
                return strval($value);
            
            default:
                if (! self::isInternalClass($type)) {
                    return $value;
                }
        }
        
        $rf = new \ReflectionClass($type);
        if ($rf->name == 'DateTime') {
            return self::convertDate($value);
        }
        
        throw new OrmException("Unknown type {type}", array(
            'type' => $type
        ));
    }

    /**
     * Retrieve the type of column in database
     *
     * @param Orm $orm
     *            Orm instance
     * @param string $table
     *            The table where the column is part of
     * @param string $columnName
     *            The name of column to retrieve datatype of
     *            
     * @return int The datatype of column in database table
     */
    private static function getColumnType(Orm $orm, string $table, string $columnName): int
    {
        return $orm->getDbType()->getColumnType($table, $columnName, $orm);
    }

    /**
     * Convert type into database type representation
     *
     * @param int $type
     *            The type to convert from
     * @param mixed $value
     *            The value to convert from
     *            
     * @return mixed The converted value data
     */
    private static function convertToDatabaseType(int $type, $value)
    {
        switch ($type) {
            case OrmDataType::STRING:
                return strval($value);
            
            case OrmDataType::DECIMAL:
                return doubleval($value);
            
            case OrmDataType::INTEGER:
                if ($value instanceof \DateTime) {
                    return $value->getTimestamp();
                }
                return intval($value);
            
            case OrmDataType::BLOB:
                return strval($value);
            
            case OrmDataType::DATETIME:
                if ($value instanceof \DateTime) {
                    return $value->format('Y-m-d H:i:s');
                }
                return strval($value);
            
            default:
                return strval($value);
        }
    }
}
