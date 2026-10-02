<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Expression\Rendering;

use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Declaration\TypeDescriptor;
use SqlSemantics\Statement\Expression\ColumnReference;
use SqlSemantics\Statement\Expression\ScalarExpression;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\NullDomain;
use SqlSemantics\Statement\Type\SqliteChoiceDomain;
use SqlSemantics\Statement\Type\SqliteNumericDomain;
use SqlSemantics\Statement\Type\Unresolved;

/**
 * Explicit expression grouping with the same evaluation and declaration dependencies.
 * @visibility public
 * @example Retaining grouping in an expression-derived result name
 *     (new \SqlSemantics\Statement\Expression\Rendering\GroupedExpression(new \SqlSemantics\Statement\Expression\NullConstant()))->toString() // => '(NULL)'
 */
final class GroupedExpression implements ScalarExpression
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * Requires a canonical immutable operand, including any bound use sites.
     */
    public function __construct(public readonly ScalarExpression $operand, public readonly string $before = '', public readonly string $after = '')
    {
        \SqlSemantics\Statement\Validation\Check::input((new SqliteTrivia())->accepts($before) && (new SqliteTrivia())->accepts($after), 'Grouping gaps contain only complete trivia.');
        \SqlSemantics\Statement\Validation\Check::input((new \SqlSemantics\Statement\SemanticGraph())->containsOnlyValues($operand), 'A grouped expression retains only semantic values.');
    }

    /**
     * Grouping does not convert the operand domain.
     */
    public function type(): TypeDescriptor|NullDomain|Unresolved|Invalid|SqliteNumericDomain|SqliteChoiceDomain
    {
        return $this->operand->type();
    }

    /**
     * Grouping retains the operand NULL facts.
     */
    public function nullability(): Nullability
    {
        return $this->operand->nullability();
    }

    /**
     * Reads the actual operand dependencies.
     * @return list<ColumnReference>
     */
    public function references(): array
    {
        return $this->operand->references();
    }

    /**
     * Writes grouping around the actual child, never an SQL fragment.
     */
    public function toString(): string
    {
        return '(' . $this->before . $this->operand->toString() . $this->after . ')';
    }
}
