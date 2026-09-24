<?php

declare(strict_types=1);

namespace SqlSemantics\Binding;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Scalar\ScalarBinder;
use SqlSemantics\Model\Expression;

/**
 * Lowers scalar grammar nodes by their tree structure, preserving expression boundaries.
 *
 * @visibility SqlSemantics
 */
final class ExpressionBinder
{
    /**
     * Binds one expression using the namespace at its evaluation stage.
     */
    public function bind(Node|Token $node, Scope $scope, bool $rowSubquery = false): Expression
    {
        if ($node instanceof Token) {
            return $this->token($node, $scope);
        }
        $row = $this->name($node, $scope) ?? Scalar\RowBinder::bind($node, $scope);
        if ($row !== null) {
            return $row;
        }
        if (in_array($node->name, ['SelectStmt', 'select_with_parens', 'subquery', 'subselect', 'select'], true) && $scope->queries !== null) {
            return (new ScalarBinder())->subquery($node, $node, $scope, $rowSubquery);
        }
        $variable = (new Scalar\VariableBinder())->expression($node, $scope);
        if ($variable !== null) {
            return $variable;
        }
        $indirection = $this->indirection($node, $scope);
        if ($indirection !== null) {
            return $indirection;
        }
        $children = Tree::significant($node);
        $grouped = $this->transparent($children);
        if ($grouped !== null) {
            return $this->bind($grouped, $scope, $rowSubquery);
        }
        if ($node->name === 'expr' && $this->qualified($children)) {
            return $scope->column($scope->identifiers->parts($node), $node);
        }
        if (isset($children[1]) && Tree::text($children[1]) === '(') {
            return $this->call($node, $children, $scope, $rowSubquery);
        }
        $expression = Scalar\Conditional\JsonPredicateBinder::bind($node, $scope) ?? Scalar\Text\NormalizationBinder::test($node, $scope) ?? Scalar\Document\XmlBinder::document($node, $scope) ?? $this->operation($node, $children, $scope);
        if ($expression !== null) {
            return $expression;
        }

        return (new ScalarBinder())->bind($node, $scope, $rowSubquery);
    }

    /**
     * Resolves a node that is only a name: a qualified identifier, a SQLite name or a MySQL identifier, including a keyword used as one.
     */
    public function name(Node $node, Scope $scope): ?Expression
    {
        $dialect = $scope->identifiers->dialect;
        if ($node->name === 'simple_ident' || $dialect === \SqlSemantics\Dialect::Sqlite && in_array($node->name, ['nm', 'idj'], true) || $dialect === \SqlSemantics\Dialect::MySql && $node->name === 'ident') {
            return $scope->column($scope->identifiers->parts($node), $node);
        }
        return null;
    }

    /**
     * Applies subscripts and field selections to a column path, a parenthesized expression or a positional parameter.
     */
    public function indirection(Node $node, Scope $scope): ?Expression
    {
        if ($node->name === 'columnref') {
            return (new Scalar\IndirectionBinder())->column($node, $scope);
        }
        $first = $node->children[0] ?? null;
        $base = Tree::child($node, ['a_expr']) ?? ($first instanceof Token && $first->name === 'PARAM' ? $first : null);
        return $base !== null && Tree::child($node, ['opt_indirection']) !== null ? (new Scalar\IndirectionBinder())->postfix($node, $base, $scope) : null;
    }

    /**
     * Resolves a scalar terminal as a literal, parameter, or column.
     *
     * A SQLite keyword standing alone as an expression can only be there through the grammar's
     * `%fallback ID` list, so it names a column like any other identifier.
     * @throws Statement\UnclassifiedSql
     * @throws \SqlSemantics\InvalidSql
     */
    public function token(Token $token, Scope $scope): Expression
    {
        if (in_array($token->name, ['DEFAULT', 'DEFAULT_SYM'], true)) {
            throw new \SqlSemantics\InvalidSql(\SqlSemantics\Model\Validation\InputViolation::DefaultContext, $token);
        }
        $literal = (new LiteralBinder($scope->identifiers->dialect))->bind($token);
        if ($literal instanceof \SqlSemantics\Model\Scalar\Reference\Parameter && $scope->identifiers->dialect === \SqlSemantics\Dialect::PostgreSql) {
            $position = (int) substr($literal->name, 1);
            $type = $scope->queries->parameterTypes[$position - 1] ?? null;
            return $type === null ? $literal : $literal->withFacts(new \SqlSemantics\Model\Scalar\ExpressionFacts($type, \SqlSemantics\Type\Nullability::Unknown));
        }
        if ($literal !== null) {
            return $literal;
        }
        if (in_array($token->name, ['IDENT', 'IDENT_QUOTED', 'ID'], true) || $scope->identifiers->dialect === \SqlSemantics\Dialect::Sqlite && in_array($token->name, ['INDEXED', 'JOIN_KW'], true)) {
            return $scope->column([$scope->identifiers->name($token)], $token);
        }

        $context = (new Scalar\ContextValueBinder())->bind(new Node('context', 0, [$token]), $scope);
        if ($context !== null) {
            return $context;
        }
        if ($scope->identifiers->dialect === \SqlSemantics\Dialect::Sqlite && preg_match('/^[A-Za-z_][A-Za-z0-9_$]*$/D', $token->text) === 1) {
            return $scope->column([$scope->identifiers->name($token)], $token);
        }
        throw new Statement\UnclassifiedSql('Unclassified expression terminal: ' . $token->name . ' ' . $token->text);
    }

    /**
     * @param list<Node|Token> $children
     */
    public function qualified(array $children): bool
    {
        if (!in_array(count($children), [3, 5], true)) {
            return false;
        }
        foreach ($children as $index => $child) {
            if ($index % 2 === 1 && Tree::text($child) !== '.') {
                return false;
            }
            if ($index % 2 === 0 && (!$child instanceof Node || $child->name !== 'nm')) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<Node|Token> $children
     */
    public function call(Node $node, array $children, Scope $scope, bool $rowSubquery = false): Expression
    {
        return (new ScalarBinder())->bind($node, $scope, $rowSubquery);
    }

    /**
     * @param list<Node|Token> $children
     */
    public function operation(Node $node, array $children, Scope $scope): ?Expression
    {
        $rules = new ExpressionRules($scope->identifiers->dialect, $scope->diagnostics());
        if (count($children) === 2 && in_array(strtoupper(Tree::text($children[0])), ['+', '-', 'NOT'], true)) {
            return $rules->operator(Tree::text($children[0]), [$this->bind($children[1], $scope)], $node);
        }
        if (count($children) === 2 && $children[0] instanceof Node && $children[0]->name === 'not2') {
            return $rules->operator('NOT', [$this->bind($children[1], $scope)], $node);
        }
        if (count($children) >= 2) {
            $tail = strtoupper(implode(' ', array_map(Tree::text(...), array_slice($children, 1))));
            $truth = \SqlSemantics\Model\Scalar\Operator\UnaryOperator::tryFrom($tail);
            if ($scope->identifiers->dialect !== \SqlSemantics\Dialect::Sqlite && $truth?->truthTest() === true) {
                return $rules->operator($tail, [$this->bind($children[0], $scope)], $node);
            }
            if (in_array($tail, ['IS NULL', 'IS NOT NULL', 'ISNULL', 'NOTNULL'], true)) {
                return $rules->operator(in_array($tail, ['IS NULL', 'ISNULL'], true) ? 'IS NULL' : 'IS NOT NULL', [$this->bind($children[0], $scope)], $node);
            }
        }
        if (count($children) === 3 && $children[1] instanceof Node && in_array($children[1]->name, ['and', 'or'], true)) {
            return $rules->operator(strtoupper($children[1]->name), [$this->bind($children[0], $scope), $this->bind($children[2], $scope)], $node);
        }
        if (count($children) === 3 && in_array(strtoupper(Tree::text($children[1])), ['+', '-', '*', '/', '%', '||', '=', '<>', '!=', '<', '>', '<=', '>=', 'AND', 'OR', 'IS', '<=>'], true)) {
            $comparison = in_array(strtoupper(Tree::text($children[1])), ['=', '<>', '!=', '<', '>', '<=', '>=', 'IS', '<=>'], true);
            $left = $this->bind($children[0], $scope, $comparison);
            $right = $this->bind($children[2], $scope, $comparison);
            if ($comparison) {
                Scalar\QueryOperandShape::check($left, $right, $node);
            }
            return $rules->operator(Tree::text($children[1]), [$left, $right], $node);
        }

        return null;
    }

    /**
     * @param list<Node|Token> $children Significant children of a possible scalar wrapper
     */
    public function transparent(array $children): Node|Token|null
    {
        if (count($children) === 1) {
            return $children[0];
        }
        return count($children) === 3 && Tree::text($children[0]) === '(' && Tree::text($children[2]) === ')' ? $children[1] : null;
    }
}
