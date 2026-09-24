<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\Extensibility;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Query\TableOccurrence;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\Definition\Catalog\ObjectAddresses;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Relation\NamedTableReference;
use SqlSemantics\Model\Statement\Definition\Statistics as Statement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Binds extended statistics definitions and their sampling target changes.
 * @visibility SqlSemantics
 */
final class StatisticsDefinitions
{
    /**
     * CREATE STATISTICS [[IF NOT EXISTS] name] [(kinds)] ON elements FROM table.
     * Columns and expressions resolve against the single table; one expression without kinds is the univariate form.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function create(Origin $origin, Node $source, QueryContext $context): Statement\CreateStatisticsStatement|Statement\CreateExpressionStatisticsStatement
    {
        $nameNode = Tree::child($source, ['any_name', 'opt_qualified_name', 'qualified_name']);
        $name = $nameNode === null ? null : ObjectAddresses::name($nameNode, $context, 3);
        $ifNotExists = ($source->children[2] ?? null) instanceof Token;
        $kinds = self::kinds($source, $context);
        $table = self::table($source, $context, $origin->scopeId);
        $scope = new Scope($context->tables->identifiers, [$table], queries: $context);
        $elements = array_map(static fn (Node $parameter): Expression => self::element($parameter, $scope), Tree::outer(Tree::child($source, ['stats_params']) ?? throw new UnclassifiedSql('Extended statistics require their elements.'), ['stats_param']));
        try {
            if (count($elements) === 1 && $kinds === []) {
                return new Statement\CreateExpressionStatisticsStatement($origin, $name, $ifNotExists, $elements[0], $table);
            }
            return new Statement\CreateStatisticsStatement($origin, $name, $ifNotExists, $kinds, Collections::nonEmpty($elements), $table);
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::StatisticsDefinition, $source, $error);
        }
    }

    /**
     * ALTER STATISTICS [IF EXISTS] name SET STATISTICS { integer | DEFAULT }; DEFAULT is the target -1 and the server lowers larger targets to 10000.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function target(Origin $origin, Node $source, QueryContext $context): Statement\SetStatisticsTargetStatement
    {
        $name = ObjectAddresses::name(Tree::child($source, ['any_name']) ?? throw new UnclassifiedSql('ALTER STATISTICS requires its name.'), $context, 3);
        $value = Tree::child($source, ['set_statistics_value']) ?? throw new UnclassifiedSql('SET STATISTICS requires its target.');
        $text = str_replace([' ', '_'], '', Tree::text($value));
        if (strtoupper($text) !== 'DEFAULT' && preg_match('/^[+-]?[0-9]{1,9}$/D', $text) !== 1) {
            throw new InvalidSql(InputViolation::StatisticsTarget, $value);
        }
        try {
            return new Statement\SetStatisticsTargetStatement($origin, $name, ($source->children[2] ?? null) instanceof Token, strtoupper($text) === 'DEFAULT' ? -1 : min((int) $text, 10000));
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::StatisticsTarget, $value, $error);
        }
    }

    /**
     * Reads the requested kinds; repeating a kind requests it once and an unknown kind is rejected.
     * @return list<Statement\StatisticsKind>
     * @throws InvalidSql
     */
    public static function kinds(Node $source, QueryContext $context): array
    {
        $kinds = [];
        foreach (Tree::outer(Tree::child($source, ['opt_name_list']) ?? new Node('opt_name_list', 0, []), ['name']) as $name) {
            $kind = Statement\StatisticsKind::tryFrom($context->tables->identifiers->name($name->tokens()[0])) ?? throw new InvalidSql(InputViolation::StatisticsDefinition, $name);
            $kinds[$kind->value] = $kind;
        }
        return array_values($kinds);
    }

    /**
     * The FROM list is one plain table, optionally ONLY and with an alias that has no effect.
     * @throws InvalidSql
     */
    public static function table(Node $source, QueryContext $context, string $scopeId): NamedTableReference
    {
        $references = Tree::outer(Tree::child($source, ['from_list']) ?? $source, ['table_ref']);
        $reference = $references[0] ?? throw new InvalidSql(InputViolation::StatisticsDefinition, $source);
        $parts = array_map(static fn (Node|Token $part): string => $part instanceof Node ? $part->name : 'token', Tree::significant($reference));
        if (count($references) !== 1 || !in_array($parts, [['relation_expr'], ['relation_expr', 'opt_alias_clause']], true)) {
            throw new InvalidSql(InputViolation::StatisticsDefinition, $reference);
        }
        return TableOccurrence::resolve(Tree::child($reference, ['relation_expr']) ?? $reference, $context, $scopeId);
    }

    /**
     * A bare name is a column; a function call or parenthesized expression is an expression unless it is itself a column.
     * Subqueries cannot appear in statistics expressions.
     * @throws InvalidSql
     */
    public static function element(Node $parameter, Scope $scope): Expression
    {
        if (Tree::outer($parameter, ['select_with_parens', 'SelectStmt']) !== []) {
            throw new InvalidSql(InputViolation::StatisticsDefinition, $parameter);
        }
        $column = Tree::child($parameter, ['ColId']);
        if ($column !== null) {
            return $scope->column([$scope->identifiers->name($column->tokens()[0])], $column);
        }
        return (new ExpressionBinder())->bind(Tree::child($parameter, ['a_expr', 'func_expr_windowless']) ?? $parameter, $scope);
    }
}
