<?php

declare(strict_types=1);

namespace Tests\Generator;

use PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayTypeNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\NullableTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;

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
        [$declaration, $parameters] = $this->metadata->of($class);
        if ($declaration->isAbstract() || ($declaration->getMethod('__construct') !== null && $declaration->getMethod('__construct')->isPrivate())) {
            return $this->known[$key] = false;
        }
        foreach ($this->metadata->parameters($declaration) as $parameter) {
            if ($parameter->default !== null) {
                continue;
            }
            $type = $parameters[$this->metadata->parameter($parameter)] ?? null;
            if (!($type === null ? $this->native($parameter->type, $class, $depth) : $this->type($type, $class, $depth))) {
                return $this->known[$key] = false;
            }
        }

        return $this->known[$key] = true;
    }

    /**
     * @param class-string $scope
     */
    public function native(\PhpParser\Node\Identifier|\PhpParser\Node\Name|\PhpParser\Node\ComplexType|null $type, string $scope, int $depth): bool
    {
        return $type === null || $this->type((new NativeDeclaration())->node($type), $scope, $depth);
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
