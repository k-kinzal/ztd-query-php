<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Expression\Conversion;

use SqlSemantics\Statement\Declaration\Affinity;
use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Declaration\TypeDescriptor;
use SqlSemantics\Statement\Expression\ColumnReference;
use SqlSemantics\Statement\Expression\ScalarExpression;
use SqlSemantics\Statement\SemanticGraph;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\NullDomain;
use SqlSemantics\Statement\Type\SqliteCastTarget;
use SqlSemantics\Statement\Type\SqliteNumericDomain;

/**
 * An explicit SQLite conversion with a known target domain and NULL propagation.
 * @visibility public
 * @example Converting a NULL operand keeps NULL
 *     $cast = new \SqlSemantics\Statement\Expression\Conversion\SqliteCast(new \SqlSemantics\Statement\Expression\NullConstant(), new \SqlSemantics\Statement\Type\SqliteCastTarget('INTEGER'));
 *     $cast->type() // => \SqlSemantics\Statement\Type\NullDomain::Null
 */
final class SqliteCast implements ScalarExpression
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * Keeps the operand and conversion target as immutable semantic values.
     */
    public function __construct(public readonly ScalarExpression $operand, public readonly SqliteCastTarget $target)
    {
        \SqlSemantics\Statement\Validation\Check::input((new SemanticGraph())->containsOnlyValues($operand), 'A cast operand contains only semantic values.');
    }

    /**
     * The target fixes the non-NULL storage domain even when the input declaration is absent.
     */
    public function type(): TypeDescriptor|NullDomain|Invalid|SqliteNumericDomain
    {
        $input = $this->operand->type();
        if ($input instanceof Invalid || $input instanceof NullDomain) {
            return $input;
        }
        return match ($this->target->affinity) {
            Affinity::Integer => new TypeDescriptor(Builtin::Integer, affinity: Affinity::Integer),
            Affinity::Real => new TypeDescriptor(Builtin::Real, affinity: Affinity::Real),
            Affinity::Text => new TypeDescriptor(Builtin::Text, affinity: Affinity::Text),
            Affinity::Blob => new TypeDescriptor(Builtin::Blob, affinity: Affinity::Blob),
            Affinity::Numeric => SqliteNumericDomain::IntegerOrReal,
        };
    }

    /**
     * Conversion propagates its operand's NULL fact and does not repair an invalid reference.
     */
    public function nullability(): Nullability
    {
        return $this->operand->nullability();
    }

    /**
     * Retains each input lookup and its original declaration identity.
     * @return list<ColumnReference>
     */
    public function references(): array
    {
        return $this->operand->references();
    }

    /**
     * Writes a conversion from its operand and decoded target.
     */
    public function toString(): string
    {
        return 'CAST(' . $this->operand->toString() . ' AS ' . $this->target->toString() . ')';
    }
}
