<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Scalar\Operator;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\ExpressionRules;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Scalar\Operator\Qualified\QualifiedInfixOperation;
use SqlSemantics\Model\Scalar\Operator\Qualified\QualifiedPrefixOperation;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Binds PostgreSQL infix and prefix operations whose operator is written as OPERATOR(path.symbol) or is a bare symbol outside the classified built-ins.
 * @visibility SqlSemantics
 */
final class QualifiedOperatorBinder
{
    /**
     * A built-in operator named through pg_catalog or without a schema binds exactly as its plain spelling; any other operator keeps its explicit name and an unknown result.
     * @throws \SqlSemantics\InvalidSql
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function bind(Node $source, Scope $scope): ?Expression
    {
        $operator = Tree::child($source, ['qual_Op']);
        if ($scope->identifiers->dialect !== Dialect::PostgreSql || $operator === null || !in_array($source->name, ['a_expr', 'b_expr'], true)) {
            return null;
        }
        $operands = array_values(array_filter($source->children, static fn ($child): bool => $child instanceof Node && in_array($child->name, ['a_expr', 'b_expr'], true)));
        $prefix = count($operands) === 1;
        $reference = OperatorReferences::read($operator, $scope);
        $binder = new ExpressionBinder();
        $bound = array_map(static fn (Node $operand): Expression => $binder->bind($operand, $scope), $operands);
        $plain = OperatorReferences::builtin($reference, $prefix);
        if ($plain !== null && $bound !== []) {
            return (new ExpressionRules(Dialect::PostgreSql, $scope->diagnostics()))->operator($plain, $bound, $source);
        }
        $facts = new ExpressionFacts(TypeDescriptor::builtin(Dialect::PostgreSql, 'unknown'), Nullability::Unknown);
        return match (count($bound)) {
            1 => new QualifiedPrefixOperation($facts, $source, $reference, $bound[0]),
            2 => new QualifiedInfixOperation($facts, $source, $reference, $bound[0], $bound[1]),
            default => Tree::invalid($source, 'operator operands'),
        };
    }
}
