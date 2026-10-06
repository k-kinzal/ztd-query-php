<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Expression;

use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\IntervalSpan;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Parameterized;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Missing\UndeclaredRoutine;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\NullOnly;
use SqlSemantics\Statement\Type\TypeFact;

/**
 * Types the six comparison operators over catalog types.
 *
 * Rule: PG-OPERATOR-COMPARISON-001 (slice — the expression family completes
 * or replaces this with its operator table). Scope: `=`, `<>`, `<`, `>`,
 * `<=`, `>=`, unqualified or qualified with `pg_catalog`. Operators are
 * looked up by name and argument types, and a context declares no routines,
 * so a result is known only when `pg_catalog` holds an operator for exactly
 * the operand types and no schema searched before `pg_catalog` can supply
 * another one: the result is then `boolean`. A string constant or NULL
 * operand takes the type of the other operand; two such operands compare as
 * `text`. Every other combination depends on the operator's declaration.
 * An invalid operand makes the comparison invalid; a dependent operand makes
 * it depend on the same inputs. Minimum precision: `Known(boolean)` for the
 * exact catalog pairs listed here.
 * Source: https://www.postgresql.org/docs/17/typeconv-oper.html, https://www.postgresql.org/docs/17/functions-comparison.html.
 * Termination: constant work. Status: Specified.
 *
 * @visibility SqlSemantics
 */
final class ComparisonTyping
{
    /**
     * The comparison operator names.
     */
    public const OPERATORS = ['=', '<>', '<', '>', '<=', '>='];

    /**
     * The types `pg_catalog` compares with themselves by all six operators.
     */
    private const SAME = [
        'bool', 'bytea', 'char', 'name', 'int2', 'int4', 'int8', 'text', 'oid', 'tid', 'float4', 'float8', 'money', 'bpchar',
        'date', 'time', 'timetz', 'timestamp', 'timestamptz', 'interval', 'bit', 'varbit', 'numeric', 'uuid', 'macaddr',
        'macaddr8', 'inet', 'tsvector', 'tsquery', 'pg_lsn', 'jsonb', 'xid8',
    ];

    /**
     * The groups of distinct types `pg_catalog` compares with each other by all six operators.
     */
    private const CROSS = [['int2', 'int4', 'int8'], ['float4', 'float8'], ['date', 'timestamp', 'timestamptz'], ['name', 'text']];

    /**
     * Answers the type of a comparison of two operands.
     */
    public function result(AnalysisContext $context, OperatorName $operator, TypeFact $left, TypeFact $right): TypeFact
    {
        foreach ([$left, $right] as $operand) {
            if ($operand instanceof Invalid || $operand instanceof Dependent) {
                return $operand;
            }
        }
        $a = $this->base($left);
        $b = $this->base($right);
        if ($a === null || $b === null || !$this->catalog($context, $operator)) {
            return new Dependent([new UndeclaredRoutine(new QualifiedName($operator->name))]);
        }
        $a = $a === Builtin::Unknown ? ($b === Builtin::Unknown ? Builtin::Text : $b) : $a;
        $b = $b === Builtin::Unknown ? $a : $b;

        return $this->exact($a, $b) ? new Known(Builtin::Bool) : new Dependent([new UndeclaredRoutine(new QualifiedName($operator->name))]);
    }

    /**
     * Answers the catalog type an operand contributes to the lookup: `unknown` for a string constant or NULL, null for a type this table does not cover.
     */
    public function base(TypeFact $operand): ?Builtin
    {
        if ($operand instanceof NullOnly) {
            return Builtin::Unknown;
        }
        if (!$operand instanceof Known) {
            return null;
        }
        if ($operand->descriptor instanceof Parameterized) {
            return $operand->descriptor->base;
        }
        if ($operand->descriptor instanceof IntervalSpan) {
            return Builtin::Interval;
        }

        return $operand->descriptor instanceof Builtin ? $operand->descriptor : null;
    }

    /**
     * Tells whether the operator name is a comparison looked up in `pg_catalog` before any other schema.
     */
    public function catalog(AnalysisContext $context, OperatorName $operator): bool
    {
        if (!in_array($operator->name->value, self::OPERATORS, true)) {
            return false;
        }
        if ($operator->qualifiers !== []) {
            return count($operator->qualifiers) === 1 && $operator->qualifiers[0]->value === 'pg_catalog';
        }
        foreach ($context->searchPath as $schema) {
            if ($schema->value !== 'pg_temp') {
                return $schema->value === 'pg_catalog';
            }
        }

        return false;
    }

    /**
     * Tells whether `pg_catalog` holds the six comparison operators for exactly a pair of types.
     */
    public function exact(Builtin $left, Builtin $right): bool
    {
        if ($left === $right) {
            return in_array($left->value, self::SAME, true);
        }
        foreach (self::CROSS as $group) {
            if (in_array($left->value, $group, true) && in_array($right->value, $group, true)) {
                return true;
            }
        }

        return false;
    }
}
