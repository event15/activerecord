<?php

/**
 * This file is part of the beebot package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @copyright Bee4 2015
 * @author Stephane HULARD <s.hulard@chstudio.fr>
 * @package BeeBot\Entity
 */

namespace BeeBot\Entity;

use ReflectionProperty;

/**
 * Define an entity property with its specific rules
 * @package BeeBot\Entity
 */
class Property
{
    /**
     * PHP Reflection instance for the current property
     * @var ReflectionProperty
     */
    private $reflection;

    /**
     * Property name
     * @var string
     */
    private $name;

    /**
     * Setter function name that will be used to set this property
     * @var string
     */
    private $setter;

    /**
     * Getter function name that will be used to retrieve property value
     * @var string
     */
    private $getter;

    /**
     * Doc block properties
     * @var array
     */
    private $attributes;

    /**
     * True if property is readable
     * @var boolean
     */
    private $readable = false;

    /**
     * True if property is writable
     * @var boolean
     */
    private $writable = false;

    /**
     * Define property
     * @param ReflectionProperty $property
     */
    public function __construct(ReflectionProperty $property)
    {
        $property->setAccessible(true);

        $this->reflection = $property;
        $this->name = $property->getName();

        $this->setter = 'set'.ucfirst($this->name);
        $this->getter = 'get'.ucfirst($this->name);

        $this->parseDocComment($property->getDocComment());

        //Get property access (check visibility and accessor
        $class = $property->getDeclaringClass();
        if ($property->isPublic()) {
            $this->readable = true;
            $this->writable = true;
        } else {
            if ($class->hasMethod($this->getter) &&
                $class->getMethod($this->getter)->isPublic()) {
                $this->readable = true;
            }
            if ($class->hasMethod($this->setter) &&
                $class->getMethod($this->setter)->isPublic()) {
                $this->writable = true;
            }
        }
    }

    /**
     * Parse property block comment and extract meta data
     * @param string $comment
     */
    private function parseDocComment($comment)
    {
        $this->attributes = [];
        if (strlen($comment) > 0) {
            $parser = new DocBlockParser();
            $this->attributes = $parser->parse($comment);
        }
    }

    /**
     * Retrieve the parsed comments
     * @return array
     */
    public function getDocComment()
    {
        return $this->attributes;
    }

    /**
     * Retrieve property name
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Check if property is readable
     * @return boolean
     */
    public function isReadable()
    {
        return $this->readable;
    }

    /**
     * Check if property is writable
     * @return boolean
     */
    public function isWritable()
    {
        return $this->writable;
    }

    /**
     * Set the property value on an AbstractRecordModel instance
     * @param mixed $value
     * @param \BeeBot\Entity\ActiveRecord $model
     * @return \BeeBot\Entity\ActiveRecord
     * @throws \BadMethodCallException
     */
    public function set($value, ActiveRecord $model)
    {
        if (!$this->writable) {
            throw new \BadMethodCallException(sprintf(
                "You can't set this property because it is not writable: %s",
                $this->name
            ));
        }

        //If the property is public, don't worry just set
        if ($this->reflection->isPublic()) {
            $model->{$this->name} = $value;
        } else {
            //If we try to unset the value, use the setValue
            //to avoid setter behaviours
            if ($value === null) {
                $this->reflection->setValue($model, $value);
            //Else just use the setter to put value
            } else {
                $model->{$this->setter}($value);
            }
        }
        return $model;
    }

    /**
     * Get property value from an AbstractRecordModel instance
     * @param \BeeBot\Entity\ActiveRecord $model
     * @return mixed Depends on the property value
     * @throws \BadMethodCallException
     */
    public function get(ActiveRecord $model)
    {
        if (!$this->readable) {
            throw new \BadMethodCallException(sprintf(
                "You can't get this property because it is not readable: %s",
                $this->name
            ));
        }

        if ($this->reflection->isPublic()) {
            return isset($model->{$this->name})?$model->{$this->name}:null;
        } else {
            return $model->{$this->getter}();
        }
    }
}
