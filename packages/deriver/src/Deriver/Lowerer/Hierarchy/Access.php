<?php

declare(strict_types=1);

namespace Deriver\Deriver\Lowerer\Hierarchy;

use Deriver\Deriver\Lowerer\Graph\CallableGraph;
use Deriver\Deriver\Lowerer\Graph\Program;

/**
 * Resolves lexical private methods and checks PHP method visibility.
 * @visibility root
 */
final class Access
{
    /**
     * @param Program $program Captured declarations
     */
    public function __construct(public readonly Program $program)
    {
    }

    /**
     * Keeps an instance call to a lexical private declaration distinct from child methods.
     * @param string $class Runtime class
     * @param string $scope Lexical calling class
     * @param string $name Method spelling
     * @param bool $static Whether static-call syntax was used
     * @return string|null Selected source symbol
     */
    public function target(string $class, string $scope, string $name, bool $static = false): ?string
    {
        $dispatch = new Dispatch($this->program);
        $lexical = $this->program->classes()[strtolower($scope)]->methods[strtolower($name)] ?? null;
        if (!$static && $lexical !== null && $this->program->callable($lexical)?->visibility === \Deriver\Deriver\Lowerer\Declaration\Metadata\Visibility::Private && $dispatch->subtype($class, $scope)) {
            return $lexical;
        }
        return $dispatch->method($class, $name);
    }

    /**
     * Checks visibility using the declaring scope, independently of runtime dispatch.
     * @param CallableGraph $method Declared method
     * @param string $scope Calling lexical class
     * @return bool Whether the method is accessible
     */
    public function allows(CallableGraph $method, string $scope): bool
    {
        if ($method->visibility === \Deriver\Deriver\Lowerer\Declaration\Metadata\Visibility::Public || strcasecmp($method->className, $scope) === 0) {
            return true;
        }
        $dispatch = new Dispatch($this->program);
        return $scope !== '' && $method->visibility === \Deriver\Deriver\Lowerer\Declaration\Metadata\Visibility::Protected && ($dispatch->subtype($scope, $method->className) || $dispatch->subtype($method->className, $scope));
    }
}
