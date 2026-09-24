<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Scalar\Operator;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Scope;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Scalar\Conditional\PatternOperator;
use SqlSemantics\Model\Scalar\Operator\Qualified\QualifiedOperator;
use SqlSemantics\Model\Scalar\Query\ComparisonOperator;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * The operator of a PostgreSQL value op ANY|SOME|ALL (array or query) comparison, with the negation of NOT LIKE and NOT ILIKE.
 * @visibility SqlSemantics
 */
final class QuantifiedOperator
{
    /**
     * Built-in operators whose result is never boolean.
     */
    public const NON_BOOLEAN = ['+', '-', '*', '/', '%', '^', '||', '->', '->>'];

    /**
     * Holds the classified or explicitly named operator.
     */
    public function __construct(public readonly ComparisonOperator|PatternOperator|QualifiedOperator $operator, public readonly bool $negated)
    {
    }

    /**
     * Classifies the operator of a PostgreSQL comparison against a subquery; null for another dialect or for a bare comparison operator, which keeps the spelling it was written with.
     * @throws InvalidSql
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function subquery(Node $source, Scope $scope): ?self
    {
        $operator = Tree::child($source, ['subquery_Op']);
        if ($scope->identifiers->dialect !== \SqlSemantics\Dialect::PostgreSql || $operator === null || Tree::child($operator, ['any_operator']) === null && ComparisonOperator::tryFrom(Tree::text($operator)) !== null) {
            return null;
        }
        return self::read($operator, $scope);
    }

    /**
     * Classifies a subquery_Op: built-in comparisons and LIKE or ILIKE (also spelled ~~ and ~~*) become their classified operators, and any other named operator stays explicit.
     * @throws InvalidSql
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function read(Node $operator, Scope $scope): self
    {
        $keywords = strtoupper(Tree::text($operator));
        if (Tree::child($operator, ['any_operator']) === null && in_array($keywords, ['LIKE', 'NOT LIKE', 'ILIKE', 'NOT ILIKE'], true)) {
            return new self(PatternOperator::from(str_replace('NOT ', '', $keywords)), str_starts_with($keywords, 'NOT '));
        }
        $reference = OperatorReferences::read($operator, $scope);
        $plain = OperatorReferences::builtin($reference, false);
        if ($plain === null) {
            return new self($reference, false);
        }
        if (in_array($plain, self::NON_BOOLEAN, true)) {
            throw new InvalidSql(InputViolation::QuantifiedOperator, $operator);
        }
        $pattern = PatternOperator::tryFrom(str_replace('NOT ', '', $plain));
        $comparison = ComparisonOperator::tryFrom($plain);
        return match (true) {
            $pattern !== null => new self($pattern, str_starts_with($plain, 'NOT ')),
            $comparison !== null => new self($comparison, false),
            default => new self($reference, false),
        };
    }
}
