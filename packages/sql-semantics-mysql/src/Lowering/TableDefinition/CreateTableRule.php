<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\TableDefinition;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Partition\Partitioning;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTableLike;
use SqlSemantics\Platform\MySql\Statement\Table\TableElement;
use SqlSemantics\Platform\MySql\Statement\Table\TableQuery;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Statement;

/**
 * Lowers CREATE TABLE in its column list, query and LIKE forms.
 *
 * Rule: MYSQL-CREATE-TABLE-LOWERING-001. Scope: the `create` alternative
 * `CREATE opt_table_options TABLE_SYM …`, create2, create2a, create3,
 * opt_table_options, table_options, table_option (5.x);
 * create_table_stmt, opt_create_table_options_etc,
 * opt_create_partitioning_etc, opt_duplicate_as_qe,
 * as_create_query_expression (8.0 and later). The word AS before the query
 * and the parentheses around `LIKE t` change nothing. The partitioning
 * clause comes from the table change family, the query from the query
 * family and IGNORE or REPLACE from the data manipulation family.
 * Constructs: CreateTable, CreateTableLike, TableQuery. Terminates: every
 * child is a strict subtree.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html,
 * https://dev.mysql.com/doc/refman/5.7/en/create-table.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class CreateTableRule
{
    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers the 5.x `create` alternative of CREATE TABLE.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function legacy(Form $form): Statement
    {
        if ($form->signature !== 'create: CREATE opt_table_options TABLE_SYM opt_if_not_exists table_ident create2') {
            throw ImplementationGap::production($form);
        }
        $temporary = $this->temporary($form->node(1));
        $exists = $this->lowering->options->present($form->node(3));
        $name = $this->lowering->names->qualified($form->node(4));
        $tail = $this->lowering->productions->form($form->node(5));
        $options = new TableOptionRule($this->lowering);
        $changes = $this->lowering->tableChanges;

        return match ($tail->signature) {
            'create2: ( create2a' => $this->enclosed($name, $this->lowering->productions->form($tail->node(1)), $temporary, $exists),
            'create2: opt_create_table_options opt_create_partitioning create3' => new CreateTable($name, [], $options->options($tail->node(0)), $changes->partitioning($tail->node(1)), $this->legacyQuery($tail->node(2)), $temporary, $exists),
            'create2: LIKE table_ident' => new CreateTableLike($name, $this->lowering->names->qualified($tail->node(1)), $temporary, $exists),
            'create2: ( LIKE table_ident )' => new CreateTableLike($name, $this->lowering->names->qualified($tail->node(2)), $temporary, $exists),
            default => throw ImplementationGap::production($tail),
        };
    }

    /**
     * Lowers the 5.x forms that open a parenthesis after the table name: a column list, or a query.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function enclosed(QualifiedName $name, Form $form, int $temporary, bool $exists): CreateTable
    {
        $changes = $this->lowering->tableChanges;

        return match ($form->signature) {
            'create2a: create_field_list ) opt_create_table_options opt_create_partitioning create3' => new CreateTable(
                $name,
                (new ElementRule($this->lowering))->elements($form->node(0)),
                (new TableOptionRule($this->lowering))->options($form->node(2)),
                $changes->partitioning($form->node(3)),
                $this->legacyQuery($form->node(4)),
                $temporary,
                $exists,
            ),
            'create2a: opt_create_partitioning create_select ) union_opt' => $this->partitioned($name, $changes->partitioning($form->node(0)), new TableQuery($this->lowering->queries->legacyParenthesizedQuery($form->node(1), $form->node(3))), $temporary, $exists),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Builds CREATE TABLE ... SELECT whose query is written in parentheses, with the partitioning inside them when one is written.
     */
    public function partitioned(QualifiedName $name, ?Partitioning $partitioning, TableQuery $query, int $temporary, bool $exists): CreateTable
    {
        return new CreateTable($name, [], [], $partitioning, $query, $temporary, $exists, $partitioning !== null);
    }

    /**
     * Counts the TEMPORARY words of 5.x: a node of `opt_table_options`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function temporary(Node $options): int
    {
        $form = $this->lowering->productions->form($options);
        if ($form->signature === 'opt_table_options:') {
            return 0;
        }
        if ($form->signature !== 'opt_table_options: table_options') {
            throw ImplementationGap::production($form);
        }
        $words = 0;
        foreach ((new Spine($this->lowering))->items($form->node(0), ['table_options: table_option', 'table_options: table_option table_options'], ['table_option']) as $option) {
            $word = $this->lowering->productions->form($option);
            $words += $word->signature === 'table_option: TEMPORARY' ? 1 : throw ImplementationGap::production($word);
        }

        return $words;
    }

    /**
     * Lowers the 5.x query part: a node of `create3`; none is null.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function legacyQuery(Node $part): ?TableQuery
    {
        $form = $this->lowering->productions->form($part);
        if ($form->signature === 'create3:') {
            return null;
        }
        $queries = $this->lowering->queries;
        $duplicate = $this->lowering->dml->duplicateHandling($form->node(0));
        $this->lowering->options->present($form->node(1));

        return match ($form->signature) {
            'create3: opt_duplicate opt_as create_select union_clause', 'create3: opt_duplicate opt_as create_select opt_union_clause' => new TableQuery($queries->legacyQuery($form->node(2), $form->node(3)), $duplicate),
            'create3: opt_duplicate opt_as ( create_select ) union_opt' => new TableQuery($queries->legacyParenthesizedQuery($form->node(3), $form->node(5)), $duplicate),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers CREATE TABLE of MySQL 8.0 and later: a node of `create_table_stmt`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function modern(Node $statement): Statement
    {
        $form = $this->lowering->productions->form($statement);
        $temporary = $this->lowering->options->present($form->node(1)) ? 1 : 0;
        $exists = $this->lowering->options->present($form->node(3));
        $name = $this->lowering->names->qualified($form->node(4));

        return match ($form->signature) {
            'create_table_stmt: CREATE opt_temporary TABLE_SYM opt_if_not_exists table_ident ( table_element_list ) opt_create_table_options_etc' => $this->rest($name, (new ElementRule($this->lowering))->elements($form->node(6)), $form->node(8), $temporary, $exists),
            'create_table_stmt: CREATE opt_temporary TABLE_SYM opt_if_not_exists table_ident opt_create_table_options_etc' => $this->rest($name, [], $form->node(5), $temporary, $exists),
            'create_table_stmt: CREATE opt_temporary TABLE_SYM opt_if_not_exists table_ident LIKE table_ident' => new CreateTableLike($name, $this->lowering->names->qualified($form->node(6)), $temporary, $exists),
            'create_table_stmt: CREATE opt_temporary TABLE_SYM opt_if_not_exists table_ident ( LIKE table_ident )' => new CreateTableLike($name, $this->lowering->names->qualified($form->node(7)), $temporary, $exists),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the options, partitioning and query after the 8.0 element list: a node of `opt_create_table_options_etc`.
     *
     * @param list<TableElement> $elements The elements already lowered
     * @throws ImplementationGap When a production has no rule
     */
    public function rest(QualifiedName $name, array $elements, Node $rest, int $temporary, bool $exists): CreateTable
    {
        $form = $this->lowering->productions->form($rest);
        [$options, $partitioning] = match ($form->signature) {
            'opt_create_table_options_etc: create_table_options opt_create_partitioning_etc' => [(new TableOptionRule($this->lowering))->options($form->node(0)), $form->node(1)],
            'opt_create_table_options_etc: opt_create_partitioning_etc' => [[], $form->node(0)],
            default => throw ImplementationGap::production($form),
        };
        $part = $this->lowering->productions->form($partitioning);
        [$clause, $query] = match ($part->signature) {
            'opt_create_partitioning_etc: partition_clause opt_duplicate_as_qe' => [$this->lowering->tableChanges->partitioning($part->node(0)), $part->node(1)],
            'opt_create_partitioning_etc: opt_duplicate_as_qe' => [null, $part->node(0)],
            default => throw ImplementationGap::production($part),
        };

        return new CreateTable($name, $elements, $options, $clause, $this->query($query), $temporary, $exists);
    }

    /**
     * Lowers the 8.0 query part: a node of `opt_duplicate_as_qe`; none is null.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function query(Node $part): ?TableQuery
    {
        $form = $this->lowering->productions->form($part);
        [$duplicate, $query] = match ($form->signature) {
            'opt_duplicate_as_qe:' => [null, null],
            'opt_duplicate_as_qe: duplicate as_create_query_expression' => [$this->lowering->dml->duplicateHandling($form->node(0)), $form->node(1)],
            'opt_duplicate_as_qe: as_create_query_expression' => [null, $form->node(0)],
            default => throw ImplementationGap::production($form),
        };
        if ($query === null) {
            return null;
        }
        $as = $this->lowering->productions->form($query);

        return new TableQuery($this->lowering->queries->query(match ($as->signature) {
            'as_create_query_expression: AS query_expression_with_opt_locking_clauses' => $as->node(1),
            'as_create_query_expression: query_expression_with_opt_locking_clauses' => $as->node(0),
            default => throw ImplementationGap::production($as),
        }), $duplicate);
    }
}
