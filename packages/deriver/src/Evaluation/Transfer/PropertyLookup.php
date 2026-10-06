<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Transfer;

use Deriver\ControlFlow\Program;
use Deriver\ControlFlow\PropertyDeclaration;
use Deriver\Evaluation\Call\Dispatch;
use Deriver\Evaluation\Call\Native\Properties;

/**
 * Resolves inherited properties and access rights under PHP 8.3 declaration scope.
 * @visibility root
 */
final class PropertyLookup
{
    /**
     * @param Program $program Captured class world
     */
    public function __construct(public readonly Program $program)
    {
    }

    /**
     * Finds a property while preserving a lexical private declaration's identity.
     * @param string $class Runtime class
     * @param string $scope Lexical access scope
     * @param string $name Property spelling
     * @param list<string> $seen Inheritance cycle guard
     * @return PropertyDeclaration|null Matching declaration
     */
    public function find(string $class, string $scope, string $name, array $seen = []): ?PropertyDeclaration
    {
        $lexical = $this->program->classes()[strtolower($scope)]->properties[$name] ?? null;
        if ($lexical?->visibility === 'private' && (new Dispatch($this->program))->subtype($class, $scope)) {
            return $lexical;
        }
        $key = strtolower($class);
        $declaration = $this->program->classes()[$key] ?? null;
        if ($declaration === null) {
            return (new Properties($this->program))->find($class, $name);
        }
        if (in_array($key, $seen, true)) {
            return null;
        }
        if (isset($declaration->properties[$name])) {
            return $declaration->properties[$name];
        }
        foreach ([$declaration->parent, ...($declaration->composed ? [] : $declaration->traits)] as $parent) {
            $property = $this->find($parent, '', $name, [...$seen, $key]);
            if ($property !== null) {
                return $property;
            }
        }
        return null;
    }

    /**
     * Tests visibility independently from whether the slot currently contains a value.
     * @param PropertyDeclaration|null $property Declared slot
     * @param string $scope Lexical access scope
     * @return bool Whether ordinary access is legal
     */
    public function accessible(?PropertyDeclaration $property, string $scope): bool
    {
        if ($property === null || $property->visibility === 'public') {
            return true;
        }
        if (strcasecmp($scope, $property->className) === 0) {
            return true;
        }
        $dispatch = new Dispatch($this->program);
        return $scope !== '' && $property->visibility === 'protected' && ($dispatch->subtype($scope, $property->className) || $dispatch->subtype($property->className, $scope));
    }
}
