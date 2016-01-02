<?php
namespace Nkey\Caribu\Orm;

trait OrmUtil
{
    /**
     * Include exception handling related functionality
     */
    use OrmExceptionHandler;
    
    /**
     * Include class utility functionality
     */
    use OrmClassUtil;
    
    /**
     * Include type utility functionality
     */
    use OrmTypeUtil;
}
