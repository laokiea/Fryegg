<?php

namespace EatWhat;

use EatWhat\Exceptions\EatWhatException;

/**
 * container for dependencies injection
 * 
 */
class EatWhatContainer
{
    /**
     * save the record of abstractName
     * 
     */
    private $binds = [];

    /**
     * bind clousure,name with namespace,short name to container
     * 
     */
    public function bind($abstractName, $concret = null)  
    {
        if(is_null($concret)) {
            $abstractName = $concret;
        }

        if(!$concret instanceof \Closure) {
            $concret = $this->getClosure($abstractName, $concret);
        }

        $this->setConcrete($abstractName, $concret);
    }

    /**
     *  bind("Car", "Car"); -> build("Car");
     *  bind("Car", "EatWhat\Car"); -> make("EatWhat\Car");
     *  bind("Car", "Audi"); -> make("EatWhat\Car");  // Car may be a interface, can not intantiate.
     *  bind("Car", function($c){ return $c->make("Audi"); });
     * 
     *  if $abstractName is not same as $concret, we need make to provide a actually name to function build
     */
    public function getClosure($abstractName, $concret)
    {
        return function($container) use($abstractName, $concret) {
            $method = ($abstractName == $concret ? "build" : "make");
            return $container->$method($concret);
        };
    }

    /**
     * set bind
     * 
     */
    public function setConcrete($abstractName, $concret)
    {
        $this->binds[$abstractName] = $concret;
    }

    /**
     * get concrete of abstractName
     * 
     */
    public function getConcrete($abstractName)
    {
        if(!isset($this->binds[$abstractName])) {
            return $abstractName;
        }
        return $this->binds[$abstractName];
    }

    /**
     * the unite function of making a instance
     * 
     */
    public function make($abstractName)
    {
        $concret = $this->getConcrete($abstractName);
        return $this->build($concret);
    }

    /**
     * get the instance of concrete
     * 
     */
    public function build($concret) 
    {
        if($concret instanceof \Closure) {
            return $concret($this);
        }

        $rfClass = new \ReflectionClass($concret);
        
        if(!$rfClass->isInstantiable()) {
            throw new EatWhatException($rfClass->name." Can Not Instantiate. ");
        }

        $concretConstructor = $rfClass->getConstructor();
        
        if(is_null($concretConstructor)) {
            return $rfClass->newInstanceWithoutConstructor();
        }

        $parameters = $concretConstructor->getParameters();
        $dependenciesValue = $this->getDependenciesValue($parameters);

        return $rfClass->newInstanceArgs($dependenciesValue);
    }

    /**
     * get constructor parameters's value of instance 
     * 
     */
    public function getDependenciesValue($parameters)
    {
        $dependenciesValue = [];
        foreach($parameters as $dependency) {
            $dependencyIsAClassObject = $dependency->getClass();
            if(is_null($dependencyIsAClassObject)) {
               $dependenciesValue[] = $this->getResolveNonClass($dependency);
            } else {
                $dependenciesValue[] = $this->make($dependencyIsAClassObject->getName());
            }
        }
        return $dependenciesValue;
    }

    /**
     * parameter of construcor is not a class object
     * 
     */
    public function getResolveNonClass(\ReflectionParameter $parameter)
    {
        if($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }
        throw new EatWhatException($parameter->getDeclaringFunction()." Arguments Has No Default Value. ");
    }
}