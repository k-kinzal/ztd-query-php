<?php

declare(strict_types=1);

namespace Deriver\Value;

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
 *     $id = \Deriver\Value\Term::parameter('id', 'int');
 *     [$id->kind, $id->attributes['type']] // => ['parameter', 'int']
 */
final class Term
{
    /**
     * Cached structural facts avoid expanding shared immutable subgraphs repeatedly.
     */
    private ?bool $concrete = null;
    private ?bool $confidential = null;

    /**
     * @param string $kind The expression tag
     * @param scalar|null $literal A scalar payload or stable identity
     * @param array<int|string, self> $operands Ordered child expressions
     * @param array<string, scalar|null> $attributes Semantic metadata
     * @param bool $secret Whether serialization must redact the expression
     */
    public function __construct(
        public readonly string $kind,
        public readonly int|float|string|bool|null $literal = null,
        public readonly array $operands = [],
        public readonly array $attributes = [],
        public readonly bool $secret = false,
        public readonly ?\Deriver\Result\Evidence\Node $evidence = null,
    ) {
    }

    /**
     * Creates a scalar constant.
     * @param scalar|null $value The PHP scalar
     * @param bool $secret Whether the value is confidential
     * @return self The constant
     */
    public static function constant(int|float|string|bool|null $value, bool $secret = false): self
    {
        return new self('constant', $value, secret: $secret);
    }

    /**
     * Creates a symbolic valid input, independent of a parameter's default.
     * @param string $name Stable input identity
     * @param string $type Declared PHP type
     * @return self The input
     */
    public static function parameter(string $name, string $type = 'mixed'): self
    {
        return new self('parameter', $name, attributes: ['type' => $type]);
    }

    /**
     * Creates an ordered PHP array with an optional unknown remainder.
     * @param array<int|string, self> $entries Known entries
     * @param bool $open Whether additional keys may exist
     * @return self The shape
     */
    public static function array(array $entries, bool $open = false): self
    {
        return new self('array', operands: $entries, attributes: ['open' => $open]);
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
        return new self('opaque', $reason, $dependencies, ['type' => $type, 'dependencyCoverage' => 'partial']);
    }

    /**
     * Converts explicitly supplied application data into immutable terms.
     * @param mixed $value Scalars and arrays of scalars or arrays
     * @param bool $secret Whether the supplied data is confidential
     * @param int $remainingDepth Remaining structural input depth
     * @return self The structured constant
     * @throws InvalidInputException If an object or resource is supplied
     */
    public static function fromNative(mixed $value, bool $secret = false, int $remainingDepth = 128): self
    {
        if ($remainingDepth < 1) {
            throw new InvalidInputException('Concrete inputs must be acyclic and at most 128 levels deep.');
        }
        if (is_array($value)) {
            $entries = [];
            foreach ($value as $key => $element) {
                $entries[$key] = self::fromNative($element, $secret, $remainingDepth - 1);
            }
            return new self('array', operands: $entries, attributes: ['open' => false], secret: $secret);
        }
        if (is_scalar($value) || $value === null) {
            return self::constant($value, $secret);
        }
        throw new InvalidInputException('Only scalars and arrays can be supplied as concrete inputs.');
    }

    /**
     * Reports whether the entire term is a concrete PHP value.
     * @return bool Whether native() can represent this term
     */
    public function isConcrete(): bool
    {
        /**
         * @var list<array{self, bool}> $pending
         */
        $pending = [[$this, false]];
        while ($pending !== []) {
            [$term, $ready] = array_pop($pending);
            if ($term->concrete !== null) {
                continue;
            }
            if ($term->kind === 'constant') {
                $term->concrete = true;
            } elseif ($term->kind !== 'array' || ($term->attributes['open'] ?? false) !== false) {
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
        /**
         * @var list<array{self, bool}> $pending
         */
        $pending = [[$this, false]];
        while ($pending !== []) {
            [$term, $ready] = array_pop($pending);
            if (isset($values[$term])) {
                continue;
            }
            if ($term->kind === 'constant') {
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

    /**
     * Reports secrecy inherited through an expression.
     * @return bool Whether this term contains confidential input
     */
    public function isSecret(): bool
    {
        /**
         * @var list<array{self, bool}> $pending
         */
        $pending = [[$this, false]];
        while ($pending !== []) {
            [$term, $ready] = array_pop($pending);
            if ($term->confidential !== null) {
                continue;
            }
            if ($term->secret) {
                $term->confidential = true;
            } elseif (!$ready) {
                $pending[] = [$term, true];
                foreach ($term->operands as $child) {
                    $pending[] = [$child, false];
                }
            } else {
                $term->confidential = false;
                foreach ($term->operands as $child) {
                    $term->confidential = $term->confidential || $child->confidential === true;
                }
            }
        }
        return $this->confidential === true;
    }
}
