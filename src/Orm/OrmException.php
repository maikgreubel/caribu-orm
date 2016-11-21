<?php
namespace Nkey\Caribu\Orm;

/**
 * Derived exception for the Caribu package
 *
 * This class is part of Caribu package
 *
 * @author Maik Greubel <greubel@nkey.de>
 */
class OrmException extends \Generics\GenericsException
{

    /**
     * Create a new OrmException instance
     *
     * @param string $message
     *            The message to throw
     * @param array $context
     *            Optional context key-value-pairs
     * @param int $number
     *            Optional exception code
     * @param \Exception $previous
     *            Optional previous occured exception to embed
     */
    public function __construct(string $message, array $context = array(), int $number = 0, \Exception $previous = null)
    {
        parent::__construct($message, $context, $number, $previous);
    }

    /**
     * Throw derived ORMException setting the previous as internal
     *
     * @param \Exception $exception
     *            The original exception
     * @param string $message
     *            Optional message to embed
     * @param int $code
     *            Optional exception code to embed
     *            
     * @throws OrmException
     */
    public static function fromPrevious(\Exception $exception, string $message = null, int $code = 0)
    {
        return new self("Exception {type} occured:{message}", array(
            'type' => get_class($exception),
            'message' => (is_null($message) ? "" : " {$message}")
        ), ($code == 0 ? $exception->getCode() : $code), $exception);
    }
}
