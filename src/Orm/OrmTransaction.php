<?php
namespace Nkey\Caribu\Orm;

use \Exception;
use \PDO;

/**
 * Transaction related functionality
 *
 * This class is part of Caribu package
 *
 * @author Maik Greubel <greubel@nkey.de>
 */
trait OrmTransaction
{
    /**
     * Include the connection related functionality
     */
    use OrmConnection;

    /**
     * The stack of open transactions
     *
     * @var int
     */
    private $transactionStack = 0;

    /**
     * Begin a new transaction
     *
     * @return PDO
     */
    private function startTX()
    {
        if (null == $this->connection) {
            $this->connection = $this->getConnection();
        }

        if (!$this->connection->inTransaction()) {
            $this->connection->beginTransaction();
        }

        $this->transactionStack++;

        return $this->connection;
    }

    /**
     * Try to commit the complete transaction stack
     *
     * @throws OrmException
     * @throws PDOException
     */
    private function commitTX()
    {
        if (!$this->connection->inTransaction()) {
            throw new OrmException("Transaction is not open");
        }

        $this->transactionStack--;

        if ($this->transactionStack === 0) {
            $this->connection->commit();
        }
    }

    /**
     * Rollback the complete stack
     *
     * @return OrmException either previous exception or new occured during rollback containing previous
     */
    private function rollBackTX(PDO $connection, Exception $previousException = null)
    {
        $this->transactionStack = 0; // Yes, we just ignore any error and reset the transaction stack here

        if (!$connection) {
            $previousException = new OrmException("Connection not open", array(), 101, $previousException);
        }

        if (!$connection->inTransaction()) {
            $previousException = new OrmException("Transaction not open", array(), 102, $previousException);
        }

        try {
            if (!$connection->rollBack()) {
                $previousException = new OrmException("Could not rollback!", array(), 103, $previousException);
            }
        } catch (PDOException $ex) {
            $previousException = OrmException::fromPrevious($ex);
        }

        return $previousException;
    }
}
