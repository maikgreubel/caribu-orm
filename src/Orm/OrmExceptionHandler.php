<?php
namespace Nkey\Caribu\Orm;

/**
 * Exception handling functionality
 *
 * This class is part of Caribu package
 *
 * @author Maik Greubel <greubel@nkey.de>
 */
trait OrmExceptionHandler
{

    /**
     * Handle a previous occured pdo exception
     *
     * @param \Nkey\Caribu\Orm\Orm $orm
     *            The orm instance
     * @param \PDOStatement $statement
     *            The statement which caused the exception to rollback
     * @param \Exception $ex
     *            The exception cause
     * @param string $message
     *            Optional message
     * @param int $code
     *            Optional code
     *            
     * @return OrmException
     */
    private static function handleException(Orm $orm, $statement, \Exception $ex, string $message = null, int $code = 0)
    {
        $toThrow = OrmException::fromPrevious($ex, $message, $code);
        
        try {
        	if ($statement != null && $statement instanceof \PDOStatement) {
                $statement->closeCursor();
            }
            unset($statement);
        } catch (\PDOException $cex) {
            // Ignore close cursor exception
        }
        
        $toThrow = $orm->rollBackTX($toThrow);
        
        return $toThrow;
    }
}
