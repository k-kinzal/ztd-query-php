<?php

declare(strict_types=1);

namespace Fuzz\Structure;

use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprIntegerNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprStringNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayTypeNode;
use PHPStan\PhpDocParser\Ast\Type\ConstTypeNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\NullableTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;
use ReflectionException;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use RuntimeException;

/**
 * Interprets PHP constructor types, including PHPDoc collection element types.
 */
final class Inhabitants
{
    /**
     * @param Construction $construction The recursive public-constructor sampler
     */
    public function __construct(public readonly Construction $construction)
    {
    }

    /**
     * @param class-string $scope The class whose PHP type is interpreted
     * @throws RuntimeException When type metadata has no generator rule
     * @throws ReflectionException When a nested public constructor cannot be invoked
     */
    public function native(?ReflectionType $type, string $scope, int $depth): mixed
    {
        if ($type instanceof ReflectionUnionType) {
            return $this->native($this->construction->choices->one(array_values(array_filter($type->getTypes(), fn (ReflectionType $alternative): bool => $this->construction->depth->native($alternative, $scope, $depth)))), $scope, $depth);
        }
        if (!$type instanceof ReflectionNamedType) {
            throw new RuntimeException('Unsupported native constructor type in ' . $scope);
        }
        if ($type->allowsNull() && ((!$type->isBuiltin() && !$this->construction->depth->object($this->construction->metadata->resolve($type->getName(), $scope, true), $depth - 1)) || $this->construction->choices->integer(0, 1) === 0)) {
            return null;
        }

        return $this->atom($type->getName(), $scope, $depth, true);
    }

    /**
     * @param class-string $scope The class whose PHP type is interpreted
     * @throws RuntimeException When type metadata has no generator rule
     * @throws ReflectionException When a nested public constructor cannot be invoked
     */
    public function generate(TypeNode $type, string $scope, int $depth): mixed
    {
        $choices = $this->construction->choices;
        if ($type instanceof UnionTypeNode) {
            return $this->generate($choices->one(array_values(array_filter($type->types, fn (TypeNode $alternative): bool => $this->construction->depth->type($alternative, $scope, $depth)))), $scope, $depth);
        }
        if ($type instanceof NullableTypeNode) {
            return !$this->construction->depth->type($type->type, $scope, $depth) || $choices->integer(0, 1) === 0 ? null : $this->generate($type->type, $scope, $depth);
        }
        if ($type instanceof IdentifierTypeNode) {
            return $this->atom($type->name, $scope, $depth);
        }
        if ($type instanceof ArrayTypeNode) {
            return $this->sequence($type->type, $scope, $depth, 0);
        }
        if ($type instanceof GenericTypeNode) {
            return $this->generic($type, $scope, $depth);
        }
        if ($type instanceof ArrayShapeNode) {
            return $this->shape($type, $scope, $depth);
        }
        if ($type instanceof ConstTypeNode) {
            if ($type->constExpr instanceof ConstExprIntegerNode) {
                return (int) $type->constExpr->value;
            }
            if ($type->constExpr instanceof ConstExprStringNode) {
                return $type->constExpr->value;
            }
        }

        throw new RuntimeException('Unsupported PHPDoc constructor type ' . $type . ' in ' . $scope);
    }

    /**
     * @param class-string $scope The class whose PHP type is interpreted
     * @throws RuntimeException When type metadata has no generator rule
     * @throws ReflectionException When a nested public constructor cannot be invoked
     */
    public function atom(string $name, string $scope, int $depth, bool $native = false): mixed
    {
        $choices = $this->construction->choices;
        if (in_array($name, ['string', 'literal-string', 'non-empty-string'], true)) {
            $values = ['a', 'b', 't', 'x', '0', '1', '2', '3', '10', '1.5', 'utf8mb4', 'utf8mb4_0900_ai_ci', '2024-01-01', '2024-01-01 01:02:03', 'A', '00000', '12345', 'ABCDE', 'A1B2C', 'PRIMARY', 'a b', "'", '\\', 'あ'];

            return $choices->one($name === 'non-empty-string' ? $values : ['', ...$values]);
        }
        if ($name === 'numeric-string') {
            return $choices->one(['0', '1', '-1', '2', '10', '1.5', '1e2']);
        }
        if (in_array($name, ['int', 'positive-int', 'non-negative-int'], true)) {
            return $choices->integer($name === 'positive-int' ? 1 : ($name === 'int' ? -1 : 0), 4);
        }
        if ($name === 'float') {
            return $choices->integer(-10, 10) / 2.0;
        }
        if ($name === 'bool') {
            return $choices->integer(0, 1) === 1;
        }
        if (in_array($name, ['null', 'true', 'false'], true)) {
            return match ($name) {
                'null' => null, 'true' => true, 'false' => false
            };
        }
        if ($name === 'array-key') {
            return $choices->one([0, 1, 'a', 'b']);
        }
        if ($name === 'scalar') {
            return $choices->one([0, 1.5, 'a', true]);
        }
        if ($name === 'object') {
            return $this->construction->object($choices->one($this->construction->catalogue->classes), $depth - 1);
        }

        return $this->construction->object($this->construction->metadata->resolve($name, $scope, $native), $depth - 1);
    }

    /**
     * @param class-string $scope The class whose PHP type is interpreted
     * @throws RuntimeException When type metadata has no generator rule
     * @throws ReflectionException When a nested public constructor cannot be invoked
     * @return list<mixed>
     */
    public function sequence(TypeNode $element, string $scope, int $depth, int $minimum): array
    {
        $values = [];
        if (!$this->construction->depth->type($element, $scope, $depth)) {
            if ($minimum === 0) {
                return [];
            }
            throw new Exhausted('A nonempty collection has no element at this depth.');
        }
        $size = $this->construction->choices->integer($minimum, 3);
        for ($index = 0; $index < $size; $index++) {
            $values[] = $this->generate($element, $scope, $depth);
        }

        return $values;
    }

    /**
     * @param class-string $scope The class whose PHP type is interpreted
     * @throws RuntimeException When type metadata has no generator rule
     * @throws ReflectionException When a nested public constructor cannot be invoked
     */
    public function generic(GenericTypeNode $type, string $scope, int $depth): mixed
    {
        $name = $type->type->name;
        if (in_array($name, ['list', 'non-empty-list'], true) && count($type->genericTypes) === 1) {
            return $this->sequence($type->genericTypes[0], $scope, $depth, $name === 'list' ? 0 : 1);
        }
        if (in_array($name, ['array', 'non-empty-array'], true)) {
            return $this->map($type, $scope, $depth);
        }
        if ($name === 'int' && count($type->genericTypes) === 2) {
            $lower = $type->genericTypes[0];
            $upper = $type->genericTypes[1];
            $minimum = (string) $lower === 'min' ? -4 : $this->generate($lower, $scope, $depth);
            $maximum = (string) $upper === 'max' ? max(4, is_int($minimum) ? $minimum : 0) : $this->generate($upper, $scope, $depth);
            if (!is_int($minimum) || !is_int($maximum)) {
                throw new RuntimeException('An integer range needs integer bounds.');
            }

            return $this->construction->choices->integer($minimum, $maximum);
        }

        throw new RuntimeException('Unsupported constructor generic ' . $type . ' in ' . $scope);
    }

    /**
     * @param class-string $scope The class whose PHP type is interpreted
     * @throws RuntimeException When type metadata has no generator rule
     * @throws ReflectionException When a nested public constructor cannot be invoked
     * @return array<array-key, mixed>
     */
    public function map(GenericTypeNode $type, string $scope, int $depth): array
    {
        $arguments = $type->genericTypes;
        $element = $arguments[count($arguments) - 1] ?? throw new RuntimeException('An array needs an element type.');
        $values = $this->sequence($element, $scope, $depth, $type->type->name === 'array' ? 0 : 1);
        if (count($arguments) === 1) {
            return $values;
        }
        $map = [];
        foreach ($values as $value) {
            $key = $this->generate($arguments[0], $scope, $depth);
            if (!is_int($key) && !is_string($key)) {
                throw new RuntimeException('An array key must be an integer or a string.');
            }
            $map[$key] = $value;
        }

        return $map;
    }

    /**
     * @param class-string $scope The class whose PHP type is interpreted
     * @throws RuntimeException When type metadata has no generator rule
     * @throws ReflectionException When a nested public constructor cannot be invoked
     * @return array<array-key, mixed>
     */
    public function shape(ArrayShapeNode $type, string $scope, int $depth): array
    {
        $values = [];
        foreach ($type->items as $item) {
            if ($item->optional && (!$this->construction->depth->type($item->valueType, $scope, $depth) || $this->construction->choices->integer(0, 1) === 0)) {
                continue;
            }
            $key = $item->keyName === null ? count($values) : ($item->keyName instanceof ConstExprIntegerNode ? (int) $item->keyName->value : ($item->keyName instanceof ConstExprStringNode ? $item->keyName->value : $item->keyName->name));
            $values[$key] = $this->generate($item->valueType, $scope, $depth);
        }

        return $values;
    }
}
