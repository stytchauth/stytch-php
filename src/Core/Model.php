<?php

namespace Stytch\Core;

/**
 * Base model class for all Stytch data structures
 */
abstract class Model
{
    /**
     * Create a model instance from an array
     * 
     * @param array $data
     * @return static
     */
    public static function fromArray(array $data): static
    {
        $reflection = new \ReflectionClass(static::class);
        $constructor = $reflection->getConstructor();
        
        if (!$constructor) {
            return new static();
        }
        
        $params = [];
        foreach ($constructor->getParameters() as $param) {
            $name = $param->getName();
            $value = $data[$name] ?? null;
            
            // Handle optional parameters
            if ($value === null && $param->isDefaultValueAvailable()) {
                $value = $param->getDefaultValue();
            }
            
            $params[] = $value;
        }
        
        return new static(...$params);
    }

    /**
     * Convert model to array
     * 
     * @return array
     */
    public function toArray(): array
    {
        $result = [];
        
        foreach (get_object_vars($this) as $key => $value) {
            if ($value instanceof self) {
                $result[$key] = $value->toArray();
            } elseif (is_array($value)) {
                $result[$key] = array_map(function ($item) {
                    return $item instanceof self ? $item->toArray() : $item;
                }, $value);
            } else {
                $result[$key] = $value;
            }
        }
        
        return $result;
    }
}