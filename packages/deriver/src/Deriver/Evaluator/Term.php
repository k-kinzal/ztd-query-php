<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator;

use Deriver\Exception\InvalidInputException;
use WeakMap;

/**
 * An immutable expression, partial structure, or symbolic input.
 *
 * Arrays retain PHP key types and insertion order. A cell denotes a shared PHP
 * reference; an object denotes identity independently of the variable storing it.
 *
 * @visibility public
 * @example Keeping an input in an expression
 *     $id = \Deriver\Deriver\Evaluator\Term::parameter('id', 'int');
 *     [$id->kind === \Deriver\Deriver\Evaluator\TermKind::Parameter, $id->attributes['type']] // => [true, 'int']
 */
final class Term
{
    /**
     * Memoized structural facts avoid expanding shared immutable subgraphs repeatedly.
     */
    private ?bool $concrete = null;

    /**
     * @param TermKind $kind The expression category
     * @param scalar|null $literal A scalar payload or stable identity
     * @param array<int|string, self> $operands Ordered child expressions
     * @param array<string, scalar|null> $attributes Semantic metadata
     */
    public function __construct(
        public readonly TermKind $kind,
        public readonly int|float|string|bool|null $literal = null,
        public readonly array $operands = [],
        public readonly array $attributes = [],
    ) {
    }

    /**
     * Creates a scalar constant.
     * @param scalar|null $value The PHP scalar
     * @return self The constant
     */
    public static function constant(int|float|string|bool|null $value): self
    {
        return new self(TermKind::Constant, $value);
    }

    /**
     * Creates a symbolic valid input, independent of a parameter's default.
     * @param string $name Stable input identity
     * @param string $type Declared PHP type
     * @return self The input
     */
    public static function parameter(string $name, string $type = 'mixed'): self
    {
        return new self(TermKind::Parameter, $name, attributes: ['type' => $type]);
    }

    /**
     * Creates an ordered PHP array with an optional unknown remainder.
     * @param array<int|string, self> $entries Known entries
     * @param bool $open Whether additional keys may exist
     * @return self The shape
     */
    public static function array(array $entries, bool $open = false): self
    {
        return new self(TermKind::Array, operands: $entries, attributes: ['open' => $open]);
    }

    /**
     * Retains an unexplored part together with its known dependencies.
     * @param string $reason A diagnostic code
     * @param string $type A justified type bound
     * @param list<self> $dependencies Known, potentially incomplete dependencies
     * @return self The residual
     */
    public static function opaque(string $reason, string $type = 'mixed', array $dependencies = []): self
    {
        return new self(TermKind::Opaque, $reason, $dependencies, ['type' => $type, 'dependencyCoverage' => 'partial']);
    }

    /**
     * Converts explicitly supplied application data into immutable terms.
     * @param mixed $value Scalars and arrays of scalars or arrays
     * @param int $remainingDepth Remaining structural input depth
     * @return self The structured constant
     * @throws InvalidInputException If an object or resource is supplied
     */
    public static function fromNative(mixed $value, int $remainingDepth = 128): self
    {
        if ($remainingDepth < 1) {
            throw new InvalidInputException('Concrete inputs must be acyclic and at most 128 levels deep.');
        }
        if (is_array($value)) {
            $entries = [];
            foreach ($value as $key => $element) {
                $entries[$key] = self::fromNative($element, $remainingDepth - 1);
            }
            return new self(TermKind::Array, operands: $entries, attributes: ['open' => false]);
        }
        if (is_scalar($value) || $value === null) {
            return self::constant($value);
        }
        throw new InvalidInputException('Only scalars and arrays can be supplied as concrete inputs.');
    }

    /**
     * Reports whether the entire term is a concrete PHP value.
     * @return bool Whether native() can represent this term
     */
    public function isConcrete(): bool
    {
        /** @var list<array{self, bool}> $pending */
        $pending = [[$this, false]];
        while ($pending !== []) {
            [$term, $ready] = array_pop($pending);
            if ($term->concrete !== null) {
                continue;
            }
            if ($term->kind === TermKind::Constant) {
                $term->concrete = true;
            } elseif ($term->kind !== TermKind::Array || ($term->attributes['open'] ?? false) !== false) {
                $term->concrete = false;
            } elseif (!$ready) {
                $pending[] = [$term, true];
                foreach ($term->operands as $child) {
                    $pending[] = [$child, false];
                }
            } else {
                $term->concrete = true;
                foreach ($term->operands as $child) {
                    $term->concrete = $term->concrete && $child->concrete === true;
                }
            }
        }
        return $this->concrete === true;
    }

    /**
     * Extracts an entirely concrete value. Symbolic values remain in the graph.
     * @return mixed A PHP scalar or array
     * @throws InvalidInputException When the term is not concrete
     */
    public function native(): mixed
    {
        if (!$this->isConcrete()) {
            throw new InvalidInputException('A symbolic or partial term has no single concrete value.');
        }
        $values = new WeakMap();
        /** @var list<array{self, bool}> $pending */
        $pending = [[$this, false]];
        while ($pending !== []) {
            [$term, $ready] = array_pop($pending);
            if (isset($values[$term])) {
                continue;
            }
            if ($term->kind === TermKind::Constant) {
                $values[$term] = $term->literal;
            } elseif (!$ready) {
                $pending[] = [$term, true];
                foreach ($term->operands as $child) {
                    $pending[] = [$child, false];
                }
            } else {
                $native = [];
                foreach ($term->operands as $key => $child) {
                    $native[$key] = $values[$child];
                }
                $values[$term] = $native;
            }
        }
        return $values[$this];
    }
}
