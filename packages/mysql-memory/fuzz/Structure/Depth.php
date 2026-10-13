<?php

declare(strict_types=1);

namespace Fuzz\Structure;

use PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayTypeNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\NullableTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;

/**
 * Checks whether constructor types have an inhabitant within a depth budget.
 *
 * Only type metadata participates. Constructor predicates may still reject a
 * choice. Unknown metadata remains eligible so the interpreter reports it;
 * neither SQL analysis nor execution is used to remove an alternative.
 */
final class Depth
{
    /**
     * @var array<string, bool>
     */
    private array $known = [];

    /**
     * Uses the same complete inventory and type metadata as construction.
     */
    public function __construct(public readonly Catalogue $catalogue, public readonly Metadata $metadata)
    {
    }

    /**
     * @param class-string $class
     */
    public function object(string $class, int $depth): bool
    {
        if ($depth < 0) {
            return false;
        }
        $key = $class . ':' . $depth;
        if (isset($this->known[$key])) {
            return $this->known[$key];
        }
        if (enum_exists($class)) {
            return $this->known[$key] = true;
        }
        if (interface_exists($class)) {
            foreach ($this->catalogue->implementations($class) as $implementation) {
                if ($this->object($implementation, $depth)) {
                    return $this->known[$key] = true;
                }
            }

            return $this->known[$key] = false;
        }
        [$reflection, $parameters] = $this->metadata->of($class);
        if (!$reflection->isInstantiable()) {
            return $this->known[$key] = false;
        }
        foreach ($reflection->getConstructor()?->getParameters() ?? [] as $parameter) {
            if ($parameter->isDefaultValueAvailable()) {
                continue;
            }
            $type = $parameters[$parameter->getName()] ?? null;
            if (!($type === null ? $this->native($parameter->getType(), $class, $depth) : $this->type($type, $class, $depth))) {
                return $this->known[$key] = false;
            }
        }

        return $this->known[$key] = true;
    }

    /**
     * @param class-string $scope
     */
    public function native(?ReflectionType $type, string $scope, int $depth): bool
    {
        if ($type === null || $type->allowsNull()) {
            return true;
        }
        if ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $alternative) {
                if ($this->native($alternative, $scope, $depth)) {
                    return true;
                }
            }

            return false;
        }

        return !$type instanceof ReflectionNamedType || $type->isBuiltin() || $this->object($this->metadata->resolve($type->getName(), $scope, true), $depth - 1);
    }

    /**
     * @param class-string $scope
     */
    public function type(TypeNode $type, string $scope, int $depth): bool
    {
        if ($type instanceof NullableTypeNode || $type instanceof ArrayTypeNode) {
            return true;
        }
        if ($type instanceof UnionTypeNode) {
            foreach ($type->types as $alternative) {
                if ($this->type($alternative, $scope, $depth)) {
                    return true;
                }
            }

            return false;
        }
        if ($type instanceof IdentifierTypeNode) {
            return $this->atom($type->name, $scope, $depth);
        }
        if ($type instanceof GenericTypeNode && in_array($type->type->name, ['non-empty-list', 'non-empty-array'], true)) {
            foreach ($type->genericTypes as $argument) {
                if (!$this->type($argument, $scope, $depth)) {
                    return false;
                }
            }
        }
        if ($type instanceof ArrayShapeNode) {
            foreach ($type->items as $item) {
                if (!$item->optional && !$this->type($item->valueType, $scope, $depth)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param class-string $scope
     */
    public function atom(string $name, string $scope, int $depth): bool
    {
        if (in_array($name, ['string', 'literal-string', 'non-empty-string', 'numeric-string', 'int', 'positive-int', 'non-negative-int', 'float', 'bool', 'null', 'true', 'false', 'array-key', 'scalar'], true)) {
            return true;
        }
        if ($name === 'object') {
            return $depth > 0;
        }

        return $this->object($this->metadata->resolve($name, $scope, false), $depth - 1);
    }
}
