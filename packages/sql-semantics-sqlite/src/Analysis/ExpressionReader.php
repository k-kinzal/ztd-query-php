<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Analysis;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Platform\Sqlite\IdentifierReader;
use SqlSemantics\Statement\Expression\BooleanReference;
use SqlSemantics\Statement\Expression\ColumnReference;
use SqlSemantics\Statement\Expression\NullConstant;
use SqlSemantics\Statement\Expression\ScalarExpression;
use SqlSemantics\Statement\Expression\SqliteBetween;
use SqlSemantics\Statement\Expression\SqliteBinary;
use SqlSemantics\Statement\Expression\SqliteBinaryOperator;
use SqlSemantics\Statement\Expression\SqliteInList;
use SqlSemantics\Statement\Expression\SqliteUnary;
use SqlSemantics\Statement\Expression\SqliteUnaryOperator;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Identifier\Quote;
use SqlSemantics\Statement\Reference\ResolvedColumn;
use SqlSemantics\Statement\Relation\Scope;

/**
 * Resolves scalar operations and their column references in one semantic scope.
 * @visibility SqlSemantics
 */
final class ExpressionReader
{
    /**
     * Separates numeric and NULL constants from names with possible truth alternatives.
     */
    public function read(Node $source, Scope $scope): ScalarExpression
    {
        $term = Tree::child($source, ['term']);
        if ($term !== null) {
            assert(count($term->tokens()) === 1, 'A literal term has one terminal.');
            return (new LiteralExpressionReader())->read($term->tokens()[0]);
        }
        $children = Tree::significant($source);
        if (count($children) === 3 && $children[0] instanceof Token && $children[0]->text === '(' && $children[1] instanceof Node && $children[1]->name === 'expr') {
            return $this->read($children[1], $scope);
        }
        if (count($children) === 2 && $children[0] instanceof Token && $children[1] instanceof Node && $children[1]->name === 'expr') {
            $operator = SqliteUnaryOperator::tryFrom(strtoupper($children[0]->text));
            if ($operator !== null) {
                return new SqliteUnary($operator, $this->read($children[1], $scope));
            }
        }
        $operation = $this->infix($source, $scope) ?? $this->predicate($source, $scope);
        if ($operation !== null) {
            return $operation;
        }
        $column = $this->column($source, $scope);
        return $column->qualifier === null && $column->name->quote === Quote::None && in_array(strtoupper($column->name->value), ['TRUE', 'FALSE'], true)
            ? new BooleanReference($column)
            : $column;
    }

    /**
     * Resolves built-in binary operations while preserving operand order.
     */
    public function infix(Node $source, Scope $scope): ?SqliteBinary
    {
        $children = Tree::significant($source);
        $first = $children[0] ?? null;
        $last = $children[count($children) - 1] ?? null;
        if ($first instanceof Node && $first->name === 'expr' && $last instanceof Node && $last->name === 'expr' && count($children) >= 3) {
            $operator = SqliteBinaryOperator::tryFrom(strtoupper(implode(' ', array_map(Tree::text(...), array_slice($children, 1, -1)))));
            if ($operator !== null) {
                return new SqliteBinary($this->read($first, $scope), $operator, $this->read($last, $scope));
            }
        }
        return null;
    }

    /**
     * Keeps membership and range tests distinct from repeated comparisons.
     */
    public function predicate(Node $source, Scope $scope): SqliteBinary|SqliteBetween|SqliteInList|null
    {
        $operands = array_values(array_filter($source->children, static fn (Node|Token $child): bool => $child instanceof Node && $child->name === 'expr'));
        $between = Tree::child($source, ['between_op']);
        if ($between !== null) {
            assert(count($operands) === 3, 'A range has a subject and two bounds.');
            return new SqliteBetween($this->read($operands[0], $scope), $this->read($operands[1], $scope), $this->read($operands[2], $scope), str_starts_with(strtoupper(Tree::text($between)), 'NOT '));
        }
        $membership = Tree::child($source, ['in_op']);
        if ($membership !== null && Tree::child($source, ['select', 'nm']) === null) {
            Tree::assertChildren($source, ['expr', 'in_op', 'exprlist'], ['(', ')']);
            assert(count($operands) === 1, 'A scalar list membership has one subject.');
            $list = Tree::child($source, ['exprlist']);
            $choices = $list === null ? [] : array_map(fn (Node $node): ScalarExpression => $this->read($node, $scope), Tree::outer($list, ['expr']));
            return new SqliteInList($this->read($operands[0], $scope), str_starts_with(strtoupper(Tree::text($membership)), 'NOT '), ...$choices);
        }
        $children = Tree::significant($source);
        $suffix = strtoupper(implode(' ', array_map(Tree::text(...), array_slice($children, 1))));
        if (count($operands) === 1 && in_array($suffix, ['ISNULL', 'NOTNULL', 'NOT NULL'], true)) {
            return new SqliteBinary($this->read($operands[0], $scope), $suffix === 'ISNULL' ? SqliteBinaryOperator::Is : SqliteBinaryOperator::IsNot, new NullConstant());
        }
        return null;
    }

    /**
     * Reads a column lookup without treating other expression forms as names.
     */
    public function column(Node $source, Scope $scope): ColumnReference
    {
        $children = Tree::significant($source);
        if (count($children) === 1 && $children[0] instanceof Token && in_array($children[0]->name, ['ID', 'INDEXED', 'JOIN_KW'], true)) {
            $parts = [$children[0]];
        } else {
            Tree::assertChildren($source, ['nm'], ['.']);
            $parts = Tree::outer($source, ['nm']);
        }
        assert(count($parts) >= 1 && count($parts) <= 3, 'A column has up to three name positions.');
        $names = array_map((new IdentifierReader())->name(...), $parts);
        $name = $names[count($names) - 1];
        $qualifier = count($names) === 1 ? null : new QualifiedName($names[count($names) - 2], count($names) === 3 ? $names[0] : null);
        $column = new ColumnReference($scope, $name, $qualifier);
        if ($qualifier === null && !$column->resolution instanceof ResolvedColumn && $name->quote === Quote::Double) {
            Tree::unsupported($source, 'identifier with a literal alternative');
        }
        return $column;
    }

}
