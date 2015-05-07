<?php
namespace Nkey\Caribu\Type;

use Generics\Util\Interpolator;

use Nkey\Caribu\Orm\Orm;
use Nkey\Caribu\Model\AbstractModel;

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
}
