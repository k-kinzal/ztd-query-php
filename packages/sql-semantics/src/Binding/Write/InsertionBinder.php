<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Write;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryNodes;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Model\BoundQuery;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\TableUse;
use SqlSemantics\Model\Write\Assignment;
use SqlSemantics\Model\Write\Insertion;

/**
 * Resolves INSERT positions to their declared destinations and checks each input row.
 *
 * @visibility SqlSemantics
 */
final class InsertionBinder
{
    /**
     * @param list<list<Expression|\SqlSemantics\Model\Write\DefaultSource>> $rows
     * @param list<BoundQuery> $queries
     * @param list<Assignment> $assignments
     */
    public function bind(Node $statement, TableUse $target, Scope $scope, array $rows, array $queries, array $assignments, ?Scope $destinations = null): Insertion
    {
        $list = $this->columnList($statement);
        $explicit = $list !== null && Tree::hasTokens($list);
        $names = !$explicit ? [] : Tree::outer($list, ['insert_column_item', 'insert_column', 'insert_ident', 'nm']);
        $columns = array_map(static fn (Node $name): Expression => (new AssignmentBinder())->target($name, $destinations ?? $scope), $names);
        $defaults = $this->defaultValues($statement);
        if ($assignments !== []) {
            $columns = array_merge(...array_map(static fn (Assignment $assignment): array => $assignment->destinations(), $assignments));
        } elseif (!$explicit && !$defaults) {
            $width = count($target->declaration->columns);
            if ($scope->identifiers->dialect === \SqlSemantics\Dialect::PostgreSql) {
                $width = count($rows[0] ?? (isset($queries[0]) ? $queries[0]->resultColumns() : $target->declaration->columns));
            }
            foreach (array_slice($target->declaration->columns, 0, $width) as $column) {
                $columns[] = $scope->column([$target->alias ?? $target->declaration->name, $column->name], $statement);
            }
        }
        $columns = array_map(static fn (Expression|\SqlSemantics\Model\Write\Storage\Path $column): \SqlSemantics\Model\Write\Storage\Path => $column instanceof Expression ? StoragePathBinder::bind($column) : $column, $columns);
        $seen = [];
        foreach ($columns as $destination) {
            $column = $destination->column();
            $name = $column->columnBinding()?->column->name ?? implode('.', $column->referenceParts());
            if ($destination instanceof \SqlSemantics\Model\Write\Storage\ColumnPath && in_array($name, $seen, true)) {
                $scope->diagnostics()->report('duplicate-insert-column', 'An INSERT column is specified more than once.', $column->source);
            }
            $seen[] = $name;
        }
        $insertion = new Insertion($target, $columns, $explicit);
        $inputs = $rows !== [] ? $rows : array_map(static fn (BoundQuery $query): array => array_map(static fn ($output): Expression => $output->expression, $query->resultColumns()), $queries);
        if ($explicit || $target->declaration->resolved) {
            foreach ($inputs as $row) {
                $this->checkRow($insertion, $row, $scope, $statement);
            }
        }
        return $insertion;
    }

    /**
     * Finds the written destination column list, including MySQL 8's list inside `(columns) query`.
     */
    public function columnList(Node $statement): ?Node
    {
        return QueryNodes::local($statement, ['insert_column_list', 'insert_columns', 'fields', 'idlist_opt'])[0] ?? Tree::child(Tree::child($statement, ['insert_query_expression']) ?? $statement, ['insert_columns']);
    }

    /**
     * Reads the input clause's own keywords without searching literals or nested queries.
     */
    public function defaultValues(Node $statement): bool
    {
        $input = Tree::child($statement, ['insert_rest']) ?? $statement;
        $tokens = array_values(array_filter($input->children, static fn ($child): bool => $child instanceof \SqlParser\Lexer\Token));
        $words = array_map(static fn ($token): string => strtoupper($token->text), $tokens);
        return in_array('DEFAULT', $words, true) && in_array('VALUES', $words, true);
    }

    /**
     * Unknown-width stars defer width checking while retaining their input query.
     *
     * @param list<Expression|\SqlSemantics\Model\Write\DefaultSource> $values
     * @throws \SqlSemantics\InvalidSql
     */
    public function checkRow(Insertion $insertion, array $values, Scope $scope, Node $source): void
    {
        if (array_filter($values, static fn (Expression|\SqlSemantics\Model\Write\DefaultSource $value): bool => $value instanceof Expression && $value->kind === ExpressionKind::Wildcard) !== []) {
            return;
        }
        if (count($values) !== count($insertion->columns) && $values !== []) {
            throw new \SqlSemantics\InvalidSql(\SqlSemantics\Model\Validation\InputViolation::InsertWidth, $source);
        }
        foreach ($insertion->columns as $index => $column) {
            if (isset($values[$index])) {
                (new AssignmentRules())->checkPath($column, $values[$index], $scope);
            }
        }
    }
}
