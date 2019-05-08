<?php

namespace EatWhat\Generator;

/**
 * Generator a middleware, storage object
 * 
 */
class Generator
{

	/**
     * generate a handle
     * 
     */
	public static function __callStatic($name, $args)
	{
        $handleClassName = "EatWhat\\".ucfirst($name)."\\".$args[0];
        $generateObject = new \ReflectionMethod($handleClassName, "generate");
        $generateParameters = [];
        if($generateObject->getParameters()) {
            unset($args[0]);
            $generateParameters = $args;
        }
        return $handleClassName::generate(...$generateParameters);
	}
}