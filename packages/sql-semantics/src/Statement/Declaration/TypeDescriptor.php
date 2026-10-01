<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Declaration;

use InvalidArgumentException;
use SqlSemantics\Statement\Element;

/**
 * A database type: its identity and every declared fact that is independent of the name.
 *
 * The identity is a Builtin case or a TypeName. Length, precision, scale,
 * signedness, character set, array dimensions, interval fields, enumeration
 * members and storage affinity are separate facts, never part of the name. A
 * fact a dialect does not have keeps its default. The dialect that read the
 * declaration only produces the Builtin cases it supports.
 *
 * @example Describing a declared type
 *     $type = new \SqlSemantics\Statement\Declaration\TypeDescriptor(\SqlSemantics\Statement\Declaration\Builtin::Numeric, precision: 10, scale: 2);
 *     $type->is(\SqlSemantics\Statement\Declaration\Builtin::Numeric) // => true
 *     $type->scale // => 2
 *
 * @visibility public
 */
final class TypeDescriptor
{
    /**
     * @param Builtin|TypeName $name Type identity
     * @param int|null $length Declared length, or display width, in the unit of the type
     * @param int|null $precision Declared precision, or fractional seconds precision
     * @param int|null $scale Declared scale; requires a precision
     * @param bool $unsigned Whether negative values are excluded; only for numeric types
     * @param bool $zerofill Whether displayed values are padded with zeros; implies unsigned
     * @param bool $binaryCollation Whether the binary collation of the character set was requested
     * @param string|null $characterSet Declared character set name; only for character types
     * @param list<Element> $members Enumeration members as typed literals; exactly the enumeration types have them
     * @param int $arrayDimensions Number of array dimensions; zero for a scalar
     * @param IntervalFields|null $intervalFields Fields an interval is restricted to
     * @param Affinity|null $affinity Storage affinity, in dialects that have one; not a runtime storage-class guarantee
     * @param NumericSize|null $effectiveNumericSize Enforced decimal size, including dialect defaults; null for unconstrained or affinity-only types
     * @throws InvalidArgumentException When the facts contradict each other
     */
    public function __construct(
        public readonly Builtin|TypeName $name,
        public readonly ?int $length = null,
        public readonly ?int $precision = null,
        public readonly ?int $scale = null,
        public readonly bool $unsigned = false,
        public readonly bool $zerofill = false,
        public readonly bool $binaryCollation = false,
        public readonly ?string $characterSet = null,
        public readonly array $members = [],
        public readonly int $arrayDimensions = 0,
        public readonly ?IntervalFields $intervalFields = null,
        public readonly ?Affinity $affinity = null,
        public readonly ?NumericSize $effectiveNumericSize = null,
    ) {
        Invariant::ensure($length === null || $length >= 0, 'A type length cannot be negative.');
        Invariant::ensure($precision === null || $precision >= 0, 'A type precision cannot be negative.');
        Invariant::ensure($scale === null || $precision !== null, 'A scale requires a precision.');
        Invariant::ensure(!$zerofill || $unsigned, 'Zero padding implies an unsigned type.');
        Invariant::ensure(!$unsigned || ($name instanceof Builtin && $name->isNumeric()), 'Only numeric types carry a sign fact.');
        Invariant::ensure((!$binaryCollation && $characterSet === null) || ($name instanceof Builtin && $name->isCharacter()), 'Only character types carry a character set or collation fact.');
        Invariant::members($members, Element::class);
        Invariant::elements(...$members);
        Invariant::ensure(($members !== []) === in_array($name, [Builtin::Enum, Builtin::Set], true), 'Exactly the enumeration types have members.');
        Invariant::ensure($arrayDimensions >= 0, 'Array dimensions cannot be negative.');
        Invariant::ensure($intervalFields === null || $name === Builtin::Interval, 'Only intervals carry interval fields.');
        Invariant::ensure($effectiveNumericSize === null || $name === Builtin::Numeric, 'Only an exact decimal type has an effective decimal size.');
        Invariant::ensure($effectiveNumericSize === null || $precision === null || $precision === 0 || $effectiveNumericSize->precision === $precision, 'Effective precision must preserve declared precision.');
        Invariant::ensure($effectiveNumericSize === null || $scale === null || $effectiveNumericSize->scale === $scale, 'Effective scale must preserve declared scale.');
    }

    /**
     * Compares the type identity, ignoring every other fact.
     */
    public function is(Builtin|TypeName $name): bool
    {
        if ($name instanceof Builtin || $this->name instanceof Builtin) {
            return $this->name === $name;
        }

        return $this->name->equals($name);
    }

    /**
     * Names the type identity for diagnostics.
     */
    public function label(): string
    {
        return $this->name instanceof Builtin ? $this->name->value : $this->name->qualifiedName();
    }
}
