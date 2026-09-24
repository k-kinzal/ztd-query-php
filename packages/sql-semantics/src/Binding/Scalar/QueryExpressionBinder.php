<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Scalar;

use LogicException;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\TypeResolution;
use SqlSemantics\Model\Expression;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Binds scalar, row, existence, membership and quantified query operands.
 * @visibility SqlSemantics
 */
final class QueryExpressionBinder
{
    /**
     * @throws LogicException
     */
    public function bind(Node $source, Node $node, Scope $scope, bool $rowSubquery = false): Expression
    {
        $context = $scope->queries;
        if ($context === null) {
            throw new LogicException('Subquery binding requires a query context.');
        }
        $query = $context->bind($node, $scope);
        $operator = $source === $node ? '' : (new ScalarBinder())->operator(Tree::significant($source));
        $symbol = $operator === '' ? 'SCALAR' : $operator;
        $exists = $symbol === 'EXISTS';
        $predicate = $exists || in_array($symbol, ['IN', 'NOT IN'], true) || preg_match('/ (ALL|ANY|SOME)$/', $symbol) === 1;
        $type = $predicate ? (new TypeResolution($scope->identifiers->dialect, $scope->diagnostics()))->boolean() : ($query->resultColumns()[0]->expression->type ?? TypeDescriptor::builtin($scope->identifiers->dialect, 'unknown'));
        $operands = [];
        if ($source !== $node) {
            foreach ($source->children as $child) {
                if ($child instanceof Node && in_array($child->name, ['a_expr', 'expr', 'bit_expr', 'bool_pri'], true)) {
                    $operands[] = (new ExpressionBinder())->bind($child, $scope);
                }
            }
        }
        $facts = new \SqlSemantics\Model\Scalar\ExpressionFacts($type, $exists ? Nullability::NotNull : Nullability::MaybeNull);
        if ($symbol === 'SCALAR') {
            return self::scalar($query, $facts, $source, $rowSubquery, $scope->identifiers->dialect);
        }
        if ($exists) {
            return new \SqlSemantics\Model\Scalar\Query\ExistsSubquery($facts, $source, $query);
        }
        return self::comparison($query, $facts, $source, $operands, $symbol, $predicate, Operator\QuantifiedOperator::subquery($source, $scope));
    }

    /**
     * Builds a scalar or tuple value from a query with a compatible known arity.
     * @throws \SqlSemantics\InvalidSql
     */
    public static function scalar(\SqlSemantics\Model\BoundQuery $query, \SqlSemantics\Model\Scalar\ExpressionFacts $facts, Node $source, bool $rowSubquery, \SqlSemantics\Dialect $dialect): Expression
    {
        $width = \SqlSemantics\Model\Validation\RowShape::width($query);
        if ($rowSubquery && ($width === null || $width > 1)) {
            $facts = new \SqlSemantics\Model\Scalar\ExpressionFacts(TypeDescriptor::builtin($dialect, 'record'), Nullability::MaybeNull);
            return new \SqlSemantics\Model\Scalar\Query\RowSubquery($facts, $source, $query);
        }
        if ($width !== null && $width !== 1) {
            throw new \SqlSemantics\InvalidSql(\SqlSemantics\Model\Validation\InputViolation::ScalarQueryWidth, $source);
        }
        return new \SqlSemantics\Model\Scalar\Query\ScalarSubquery($facts, $source, $query);
    }

    /**
     * A PostgreSQL quantified comparison passes its classified operator; otherwise the operator is read from the symbol.
     * @param list<Expression> $operands Left comparison operand
     * @throws \SqlSemantics\InvalidSql
     * @throws \SqlSemantics\Binding\Statement\UnclassifiedSql
     */
    public static function comparison(\SqlSemantics\Model\BoundQuery $query, \SqlSemantics\Model\Scalar\ExpressionFacts $facts, Node $source, array $operands, string $symbol, bool $predicate, ?Operator\QuantifiedOperator $operator = null): Expression
    {
        if (count($operands) === 1 && $predicate && !\SqlSemantics\Model\Validation\QueryComparison::compatible($operands[0], $query)) {
            throw new \SqlSemantics\InvalidSql(\SqlSemantics\Model\Validation\InputViolation::ComparisonWidth, $source);
        }
        if (count($operands) === 1 && in_array($symbol, ['IN', 'NOT IN'], true)) {
            return new \SqlSemantics\Model\Scalar\Query\InSubquery($facts, $source, $operands[0], $query, $symbol === 'NOT IN');
        }
        if (count($operands) === 1 && preg_match('/^([-+*\/%^]) (ALL|ANY|SOME)$/', $symbol) === 1) {
            throw new \SqlSemantics\InvalidSql(\SqlSemantics\Model\Validation\InputViolation::QuantifiedOperator, $source);
        }
        if (count($operands) === 1 && $operator !== null && preg_match('/ (ALL|ANY|SOME)$/', $symbol, $parts) === 1) {
            return new \SqlSemantics\Model\Scalar\Query\QuantifiedComparison($facts, $source, $operands[0], $operator->operator, \SqlSemantics\Model\Scalar\Query\Quantifier::from($parts[1]), $query, $operator->negated);
        }
        if (count($operands) === 1 && preg_match('/^(.*?) (ALL|ANY|SOME)$/', $symbol, $parts) === 1) {
            return new \SqlSemantics\Model\Scalar\Query\QuantifiedComparison($facts, $source, $operands[0], \SqlSemantics\Model\Scalar\Query\ComparisonOperator::tryFrom($parts[1]) ?? throw new \SqlSemantics\Binding\Statement\UnclassifiedSql('Unclassified quantified comparison: ' . $parts[1]), \SqlSemantics\Model\Scalar\Query\Quantifier::from($parts[2]), $query);
        }
        throw new \SqlSemantics\Binding\Statement\UnclassifiedSql('Unclassified subquery operation: ' . $symbol);
    }
}
