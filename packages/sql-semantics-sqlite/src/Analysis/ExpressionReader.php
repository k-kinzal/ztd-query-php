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
use SqlSemantics\Statement\Projection\AliasReference;
use SqlSemantics\Statement\Projection\ColumnOrAlias;
use SqlSemantics\Statement\Projection\Field;
use SqlSemantics\Statement\Projection\Fields;
use SqlSemantics\Statement\Reference\CandidateColumn;
use SqlSemantics\Statement\Reference\MissingColumn;
use SqlSemantics\Statement\Reference\ResolvedColumn;
use SqlSemantics\Statement\Relation\Scope;

/**
 * Resolves scalar operations and their column references in one semantic scope.
 * @visibility SqlSemantics
 */
final class ExpressionReader
{
    /**
     * @var list<Field>
     */
    private readonly array $aliases;

    /**
     * Only aliases present in the input query enter its lookup namespace.
     */
    public function __construct(private readonly ?Fields $projection = null, Field ...$aliases)
    {
        $this->aliases = array_values($aliases);
        foreach ($aliases as $alias) {
            assert($projection !== null && in_array($alias, $projection->items, true), 'An alias must come from this projection.');
            assert($alias->alias !== null, 'A visible alias must have a declared name.');
        }
    }

    /**
     * Separates numeric and NULL constants from names with possible truth alternatives.
     */
    public function read(Node $source, Scope $scope): ScalarExpression
    {
        assert($this->projection === null || $this->projection->scope === $scope, 'Alias and input lookup must share a scope.');
        $conversion = (new ConversionReader())->read($source, $scope, $this);
        if ($conversion !== null) {
            return $conversion;
        }
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
        return $this->reference($source, $scope);
    }

    /**
     * Applies column, alias, and truth-name lookup in their database-defined order.
     */
    public function reference(Node $source, Scope $scope): ScalarExpression
    {
        $column = $this->column($source, $scope);
        $alias = $this->alias($column);
        if ($alias !== null) {
            return $alias;
        }
        if ($column->qualifier === null && !$column->resolution instanceof ResolvedColumn && $column->name->quote === Quote::Double) {
            Tree::unsupported($source, 'identifier with a literal alternative');
        }
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
     * Input columns take precedence; missing declarations retain both possible interpretations.
     */
    public function alias(ColumnReference $column): AliasReference|ColumnOrAlias|null
    {
        if ($this->projection === null || $column->qualifier !== null || (!$column->resolution instanceof MissingColumn && !$column->resolution instanceof CandidateColumn)) {
            return null;
        }
        foreach ($this->aliases as $field) {
            assert($field->alias !== null, 'A visible alias has a declared name.');
            if ($column->scope->catalog->columnNames->equal($field->alias->value, $column->name->value)) {
                $alias = new AliasReference($this->projection, $field, $column->name);
                return $column->resolution instanceof CandidateColumn ? new ColumnOrAlias($column, $alias) : $alias;
            }
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
        return new ColumnReference($scope, $name, $qualifier);
    }

}
