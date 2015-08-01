<?php
namespace Nkey\Caribu\Orm;

use \Exception;

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
     * @throws OrmException
     */
    private function rollBackTX(Exception $previousException = null)
    {
        $this->transactionStack = 0; // Yes, we just ignore any error and reset the transaction stack here

        if (!$this->connection) {
            throw new OrmException("Connection not open", array(), 101, $previousException);
        }

        if (!$this->connection->inTransaction()) {
            throw new OrmException("Transaction not open", array(), 102, $previousException);
        }

        try {
            if (!$this->connection->rollBack()) {
                throw new OrmException("Could not rollback!", array(), 103, $previousException);
            }
        } catch (PDOException $ex) {
            throw OrmException::fromPrevious($ex);
        }
    }

}