<?php

namespace yzh52521\mail;

use ReflectionClass;
use ReflectionProperty;
use think\Model;

trait SerializesModels
{
    /**
     * Prepare the instance values for serialization.
     *
     * @return array
     */
    public function __serialize()
    {
        $values = [];

        $reflectionClass = new ReflectionClass($this);

        [$class, $properties, $classLevelWithoutRelations] = [
            get_class($this),
            $reflectionClass->getProperties(),
            !empty($reflectionClass->getAttributes(WithoutRelations::class)),
        ];

        foreach ($properties as $property) {
            if ($property->isStatic()) {
                continue;
            }

            if (!$property->isInitialized($this)) {
                continue;
            }

            $value = $this->getPropertyValue($property);

            if ($property->hasDefaultValue() && $value === $property->getDefaultValue()) {
                continue;
            }

            $name = $property->getName();

            if ($value instanceof Model) {
                $value = new ModelIdentifier(get_class($value), $value->{$value->getPk()});
            }

            if ($property->isPrivate()) {
                $name = "\0{$class}\0{$name}";
            } elseif ($property->isProtected()) {
                $name = "\0*\0{$name}";
            }

            $values[$name] = $value;
        }

        return $values;
    }

    /**
     * Restore the model after serialization.
     *
     * @param array $values
     * @return void
     */
    public function __unserialize(array $values)
    {
        $properties = (new ReflectionClass($this))->getProperties();

        $class = get_class($this);

        foreach ($properties as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $name = $property->getName();

            if ($property->isPrivate()) {
                $name = "\0{$class}\0{$name}";
            } elseif ($property->isProtected()) {
                $name = "\0*\0{$name}";
            }

            if (!array_key_exists($name, $values)) {
                continue;
            }

            if ($value instanceof ModelIdentifier) {
                /** @var Model|\think\model\concern\SoftDelete $model */
                $model = $value->class;
                if (method_exists($model, 'withTrashed')) {
                    $value = $model::withTrashed()->findOrEmpty($value->id);
                } else {
                    $value = $model::findOrEmpty($value->id);
                }
            }
            $property->setValue(
                $this, $values[$name]
            );
        }
    }

    /**
     * Get the property value for the given property.
     *
     * @param \ReflectionProperty $property
     * @return mixed
     */
    protected function getPropertyValue(ReflectionProperty $property)
    {
        return $property->getValue($this);
    }
}
