<?php
namespace Nkey\Caribu\Orm;

use Generics\GenericsException;
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
     * @param string $message The message to throw
     * @param array $context Optional context key-value-pairs
     * @param number $number Optional exception code
     * @param \Exception $previous Optional previous occured exception to embed
     */
    public function __construct($message, array $context = array(), $number = 0, $previous = null)
    {
        parent::__construct($message, $context, $number, $previous);
    }

    /**
     * Throw derived ORMException setting the previous as internal
     *
     * @param \Exception $ex
     * @throws OrmException
     */
    public static function fromPrevious(\Exception $ex, $message = null, $code = 0)
    {
        return new self("Exception {type} occured:{message}", array(
            'type' => get_class($ex),
            'message' => (is_null($message) ? "" : " {$message}")
        ), ($code == 0 ? $ex->getCode() : $code), $ex);
    }
}
