<?php

declare(strict_types=1);

namespace Bermuda\Tokenizer;

use ReflectionClass;
use ReflectionEnum;

/**
 * Represents information about a PHP class declaration
 *
 * This final class contains all information about a found class declaration
 * including its full qualified name, type, modifiers, and provides virtual
 * properties for convenient access to derived information and reflection.
 *
 * @package Bermuda\Tokenizer
 */
final class ClassInfo
{
    private null|ReflectionClass|ReflectionEnum $_reflector = null;

    /**
     * Lazy-loaded reflector instance
     */
    public ReflectionClass|ReflectionEnum $reflector {
        get {
            return $this->_reflector ??= $this->createReflector();
        }
    }

    public readonly string $type;
    public readonly bool $isAbstract;
    public readonly bool $isFinal;
    public readonly bool $isReadonly;
    public readonly string $fullQualifiedName;

    /**
     * Virtual property: class name without namespace
     */
    public string $name {
        get {
            $parts = explode('\\', $this->fullQualifiedName);
            return end($parts);
        }
    }

    /**
     * Virtual property: namespace without class name
     */
    public string $namespace {
        get {
            $parts = explode('\\', $this->fullQualifiedName);
            if (count($parts) <= 1) {
                return '';
            }
            array_pop($parts);
            return implode('\\', $parts);
        }
    }

    /**
     * Virtual property: true if this is a class declaration
     */
    public bool $isClass {
        get => $this->type === 'class';
    }

    /**
     * Virtual property: true if this is an interface declaration
     */
    public bool $isInterface {
        get => $this->type === 'interface';
    }

    /**
     * Virtual property: true if this is a trait declaration
     */
    public bool $isTrait {
        get => $this->type === 'trait';
    }

    /**
     * Virtual property: true if this is an enum declaration
     */
    public bool $isEnum {
        get => $this->type === 'enum';
    }

    /**
     * Virtual property: true if this is a concrete class (not abstract)
     */
    public bool $isConcrete {
        get => $this->type === 'class' && !$this->isAbstract;
    }

    /**
     * Create a new ClassInfo instance
     *
     * @param string $fullQualifiedName Fully qualified class name
     * @param string $type Type of declaration (class, interface, trait, enum)
     * @param bool $isAbstract Whether the class is abstract
     * @param bool $isFinal Whether the class is final
     * @param bool $isReadonly Whether the class is readonly
     */
    public function __construct(
        string $fullQualifiedName,
        string $type = 'class',
        bool $isAbstract = false,
        bool $isFinal = false,
        bool $isReadonly = false
    ) {
        $this->fullQualifiedName = $fullQualifiedName;
        $this->type = $type;
        $this->isAbstract = $isAbstract;
        $this->isFinal = $isFinal;
        $this->isReadonly = $isReadonly;
    }

    /**
     * Checks if the class/interface/trait/enum exists in memory
     *
     * @return bool True if the declaration exists and can be reflected
     */
    public function exists(): bool
    {
        return match ($this->type) {
            'interface' => interface_exists($this->fullQualifiedName),
            'trait' => trait_exists($this->fullQualifiedName),
            'enum' => enum_exists($this->fullQualifiedName),
            default => class_exists($this->fullQualifiedName)
        };
    }

    /**
     * Creates appropriate reflector instance
     *
     * @return ReflectionClass|ReflectionEnum Reflector for the declaration
     * @throws \ReflectionException If class does not exist
     */
    private function createReflector(): ReflectionClass|ReflectionEnum
    {
        if (!$this->exists()) {
            throw new \ReflectionException("Class {$this->fullQualifiedName} does not exist");
        }

        return match ($this->type) {
            'enum' => new ReflectionEnum($this->fullQualifiedName),
            default => new ReflectionClass($this->fullQualifiedName)
        };
    }
}