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
