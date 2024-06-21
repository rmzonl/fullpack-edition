<?php namespace ZN\Ability;
/**
 * ZN PHP Web Framework
 * 
 * "Simplicity is the ultimate sophistication." ~ Da Vinci
 * 
 * @package ZN
 * @license MIT [http://opensource.org/licenses/MIT]
 * @author  Ozan UYKUN [ozan@znframework.com]
 */

use ZN\IS;

trait Functionalization
{
    /**
     * Magic call
     * 
     * @param string $method
     * @param array  $parameters
     * 
     * @return mixed
     */
    public function __call($method, $parameters)
    {   
        # It allows a library to cluster the desired functions within it.
        if( $standart = (static::functionalization[strtolower($method)] ?? NULL) )
        {
            return $standart(...$parameters);
        }

        $getParentClass = IS::phpVersion('8.3') ? get_parent_class($this) : get_parent_class();

        # The __call method of the parent class does not lose its functionality.
        if( method_exists($getParentClass ?: '', '__call'))
        {
            return parent::__call($method, $parameters);
        }

        return false;
    }
}
