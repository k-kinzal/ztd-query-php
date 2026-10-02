<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Expression\Subquery;

use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Declaration\TypeDescriptor;
use SqlSemantics\Statement\Expression\ColumnReference;
use SqlSemantics\Statement\Expression\ScalarExpression;
use SqlSemantics\Statement\Type\Invalid;

/**
 * Scalar membership in a query result, including its empty-relation NULL behavior.
 * @visibility public
 * @example Recognizing query membership as an expression
 *     is_a(\SqlSemantics\Statement\Expression\Subquery\SqliteInQuery::class, \SqlSemantics\Statement\Expression\ScalarExpression::class, true) // => true
 */
final class SqliteInQuery implements ScalarExpression
{
    /**
     * Keeps the subject, result relation, and negation as separate semantic operands.
     */
    public function __construct(public readonly ScalarExpression $subject, public readonly SqliteSubquery $source, public readonly bool $negated = false)
    {
        assert((new \SqlSemantics\Statement\SemanticGraph())->containsOnlyValues($subject), 'Membership retains a semantic subject.');
        assert((new \SqlSemantics\Statement\Expression\Reference\Ownership())->accepts($subject, $source->scope), 'The membership subject and subquery must have the same expression site.');
    }

    /**
     * Membership needs exactly one output when its left operand is scalar.
     */
    public function type(): TypeDescriptor|Invalid
    {
        $type = $this->subject->type();
        if ($type instanceof Invalid) {
            return $type;
        }
        return $this->source->invalid() ?? ($this->source->width() === 1 ? new TypeDescriptor(Builtin::Integer) : Invalid::ScalarSubqueryWidth);
    }

    /**
     * A NULL subject can still have a non-NULL result when the query is empty.
     */
    public function nullability(): Nullability
    {
        if ($this->type() instanceof Invalid) {
            return Nullability::Unknown;
        }
        $outputs = $this->source->query instanceof \SqlSemantics\Statement\Query\Rows ? $this->source->operands() : $this->source->outputs();
        foreach ([$this->subject, ...$outputs] as $output) {
            if ($output->nullability() !== Nullability::NotNull) {
                return Nullability::MaybeNull;
            }
        }
        return Nullability::NotNull;
    }

    /**
     * @return list<ColumnReference>
     */
    public function references(): array
    {
        return [...$this->subject->references(), ...$this->source->references()];
    }

    /**
     * Keeps query membership distinct from membership in an expression list.
     */
    public function toString(): string
    {
        return '(' . $this->subject->toString() . ')' . ($this->negated ? ' NOT' : '') . ' IN (' . $this->source->query->toString() . ')';
    }
}
