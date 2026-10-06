<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Call\Member;

use Deriver\ControlFlow\ClassConstant;
use Deriver\ControlFlow\Program;
use Deriver\Evaluation\Call\Dispatch;

/**
 * Resolves inherited constant ownership and PHP visibility without host reflection.
 * @visibility root
 */
final class Constants
{
    /**
     * @param Program $program Captured declaration world
     */
    public function __construct(public readonly Program $program)
    {
    }

    /**
     * Searches the class, its parent, and implemented interfaces in declaration order.
     * @param string $class Requested class
     * @param string $name Case-sensitive constant name
     * @return ClassConstant|null Resolved constant contract
     */
    public function find(string $class, string $name): ?ClassConstant
    {
        $pending = [$class];
        $seen = [];
        while ($pending !== []) {
            $current = array_shift($pending);
            $key = strtolower($current);
            $declaration = $this->program->classes()[$key] ?? null;
            if ($declaration === null || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            if (isset($declaration->constants[$name])) {
                return $declaration->constantDeclarations[$name] ?? new ClassConstant($declaration->name, $name);
            }
            array_unshift($pending, $declaration->parent, ...$declaration->interfaces);
        }
        return null;
    }

    /**
     * Applies constant access using its declaring class and the caller's lexical scope.
     * @param ClassConstant $constant Selected declaration
     * @param string $scope Lexical calling class, or empty outside a class
     * @return bool Whether access is permitted
     */
    public function allowed(ClassConstant $constant, string $scope): bool
    {
        if ($constant->visibility === 'public' || strcasecmp($scope, $constant->className) === 0) {
            return true;
        }
        $dispatch = new Dispatch($this->program);
        return $constant->visibility === 'protected' && $scope !== '' && ($dispatch->subtype($scope, $constant->className) || $dispatch->subtype($constant->className, $scope));
    }
}
