<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\TableDefinition;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Partition\Partitioning;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTableLike;
use SqlSemantics\Platform\MySql\Statement\Table\TableElement;
use SqlSemantics\Platform\MySql\Statement\Table\TableQuery;
use SqlSemantics\Statement\Identifier\QualifiedName;

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
     * @throws \SqlSemantics\Construction\RejectedInput When column operands contradict their type
     * @throws \SqlSemantics\Diagnostic\AnalysisException When a declared operand is invalid
     */
    public function legacy(Form $form): Statement
    {
        if ($form->signature !== 'create: CREATE opt_table_options TABLE_SYM opt_if_not_exists table_ident create2') {
            throw ImplementationGap::production($form);
        }
        $temporary = $this->temporary($form->node(1));
        $exists = $this->lowering->options->present($form->node(3));
        $name = $this->lowering->names->qualified($form->node(4));
        $this->lowering->columnTable = $name;
        $tail = $this->lowering->productions->form($form->node(5));
        $options = new TableOptionRule($this->lowering);
        $changes = $this->lowering->tableChanges;

        return match ($tail->signature) {
            'create2: ( create2a' => $this->enclosed($name, $this->lowering->productions->form($tail->node(1)), $temporary, $exists),
            'create2: opt_create_table_options opt_create_partitioning create3' => $this->create($name, [], $options->options($tail->node(0)), $changes->partitioning($tail->node(1)), $this->legacyQuery($tail->node(2)), $temporary, $exists, (new \SqlSemantics\Construction\SourceRanges())->span($form->node)[1] ?? 0, optionsCommas: (new \SqlSemantics\Platform\MySql\Lowering\Lists\OptionalCommas($this->lowering))->between($tail->node(0), ['create_table_option'])),
            'create2: LIKE table_ident' => new CreateTableLike($name, $this->lowering->names->qualified($tail->node(1)), $temporary, $exists),
            'create2: ( LIKE table_ident )' => new CreateTableLike($name, $this->lowering->names->qualified($tail->node(2)), $temporary, $exists, enclosed: true),
            default => throw ImplementationGap::production($tail),
        };
    }

    /**
     * Lowers the 5.x forms that open a parenthesis after the table name: a column list, or a query.
     *
     * @throws ImplementationGap When a production has no rule
     * @throws \SqlSemantics\Construction\RejectedInput When column operands contradict their type
     * @throws \SqlSemantics\Diagnostic\AnalysisException When a declared operand is invalid
     */
    public function enclosed(QualifiedName $name, Form $form, int $temporary, bool $exists): CreateTable
    {
        $changes = $this->lowering->tableChanges;

        return match ($form->signature) {
            'create2a: create_field_list ) opt_create_table_options opt_create_partitioning create3' => $this->create(
                $name,
                (new ElementRule($this->lowering))->elements($form->node(0)),
                (new TableOptionRule($this->lowering))->options($form->node(2)),
                $changes->partitioning($form->node(3)),
                $this->legacyQuery($form->node(4)),
                $temporary,
                $exists,
                (new \SqlSemantics\Construction\SourceRanges())->span($form->node)[1] ?? 0,
                optionsCommas: (new \SqlSemantics\Platform\MySql\Lowering\Lists\OptionalCommas($this->lowering))->between($form->node(2), ['create_table_option']),
            ),
            'create2a: opt_create_partitioning create_select ) union_opt' => $this->partitioned($name, $changes->partitioning($form->node(0)), new TableQuery($this->lowering->queries->legacyParenthesizedQuery($form->node(1), $form->node(3)), as: false), $temporary, $exists, (new \SqlSemantics\Construction\SourceRanges())->span($form->node)[1] ?? 0),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Builds CREATE TABLE ... SELECT whose query is written in parentheses, with the partitioning inside them when one is written.
     */
    public function partitioned(QualifiedName $name, ?Partitioning $partitioning, TableQuery $query, int $temporary, bool $exists, int $end): CreateTable
    {
        return $this->create($name, [], [], $partitioning, $query, $temporary, $exists, $end, $partitioning !== null);
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
            'create3: opt_duplicate opt_as create_select union_clause', 'create3: opt_duplicate opt_as create_select opt_union_clause' => new TableQuery($queries->legacyQuery($form->node(2), $form->node(3)), $duplicate, as: $form->node(1)->children !== []),
            'create3: opt_duplicate opt_as ( create_select ) union_opt' => new TableQuery($queries->legacyParenthesizedQuery($form->node(3), $form->node(5)), $duplicate, as: $form->node(1)->children !== []),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers CREATE TABLE of MySQL 8.0 and later: a node of `create_table_stmt`.
     *
     * @throws ImplementationGap When a production has no rule
     * @throws \SqlSemantics\Construction\RejectedInput When column operands contradict their type
     * @throws \SqlSemantics\Diagnostic\AnalysisException When a declared operand is invalid
     */
    public function modern(Node $statement): Statement
    {
        $form = $this->lowering->productions->form($statement);
        $temporary = $this->lowering->options->present($form->node(1)) ? 1 : 0;
        $exists = $this->lowering->options->present($form->node(3));
        $name = $this->lowering->names->qualified($form->node(4));
        $this->lowering->columnTable = $name;

        return match ($form->signature) {
            'create_table_stmt: CREATE opt_temporary TABLE_SYM opt_if_not_exists table_ident ( table_element_list ) opt_create_table_options_etc' => $this->rest($name, (new ElementRule($this->lowering))->elements($form->node(6)), $form->node(8), $temporary, $exists),
            'create_table_stmt: CREATE opt_temporary TABLE_SYM opt_if_not_exists table_ident opt_create_table_options_etc' => $this->rest($name, [], $form->node(5), $temporary, $exists),
            'create_table_stmt: CREATE opt_temporary TABLE_SYM opt_if_not_exists table_ident LIKE table_ident' => new CreateTableLike($name, $this->lowering->names->qualified($form->node(6)), $temporary, $exists),
            'create_table_stmt: CREATE opt_temporary TABLE_SYM opt_if_not_exists table_ident ( LIKE table_ident )' => new CreateTableLike($name, $this->lowering->names->qualified($form->node(7)), $temporary, $exists, enclosed: true),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the options, partitioning and query after the 8.0 element list: a node of `opt_create_table_options_etc`.
     *
     * @param list<TableElement> $elements The elements already lowered
     * @throws ImplementationGap When a production has no rule
     * @throws \SqlSemantics\Construction\RejectedInput When partitioning or query operands are refused
     */
    public function rest(QualifiedName $name, array $elements, Node $rest, int $temporary, bool $exists): CreateTable
    {
        $form = $this->lowering->productions->form($rest);
        [$options, $partitioning] = match ($form->signature) {
            'opt_create_table_options_etc: create_table_options opt_create_partitioning_etc' => [(new TableOptionRule($this->lowering))->options($form->node(0)), $form->node(1)],
            'opt_create_table_options_etc: opt_create_partitioning_etc' => [[], $form->node(0)],
            default => throw ImplementationGap::production($form),
        };
        try {
            $part = $this->lowering->productions->form($partitioning);
            [$clause, $query] = match ($part->signature) {
                'opt_create_partitioning_etc: partition_clause opt_duplicate_as_qe' => [$this->lowering->tableChanges->partitioning($part->node(0)), $part->node(1)],
                'opt_create_partitioning_etc: opt_duplicate_as_qe' => [null, $part->node(0)],
                default => throw ImplementationGap::production($part),
            };

            return $this->create($name, $elements, $options, $clause, $this->query($query, true), $temporary, $exists, (new \SqlSemantics\Construction\SourceRanges())->span($rest)[1] ?? 0, optionsCommas: $options === [] ? [] : (new \SqlSemantics\Platform\MySql\Lowering\Lists\OptionalCommas($this->lowering))->between($form->node(0), ['create_table_option']));
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            if ($failure->problem instanceof \SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\RefusedCreateTable || $failure->problem instanceof \SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\RefusedCreateTail) {
                throw $failure;
            }
            throw new \SqlSemantics\Construction\RejectedInput(new \SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\RefusedCreateTail($name, $elements, $options, $temporary, $exists, $failure->problem), $failure->end, $failure->aborts, $failure->sources);
        }
    }

    /**
     * Lowers the 8.0 query part: a node of `opt_duplicate_as_qe`; none is null.
     *
     * @throws ImplementationGap When a production has no rule
     * @throws \SqlSemantics\Construction\RejectedInput When query operands require deferred preparation
     */
    public function query(Node $part, bool $retained = false): ?TableQuery
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

        $node = match ($as->signature) {
            'as_create_query_expression: AS query_expression_with_opt_locking_clauses' => $as->node(1),
            'as_create_query_expression: query_expression_with_opt_locking_clauses' => $as->node(0),
            default => throw ImplementationGap::production($as),
        };
        if (!$retained) {
            return new TableQuery($this->lowering->queries->embedded($node), $duplicate, as: str_starts_with($as->signature, 'as_create_query_expression: AS '));
        }
        $body = (new \SqlSemantics\Platform\MySql\Lowering\Query\Shared\StoredSelections($this->lowering))->body($node) ?? $this->lowering->queries->query($node);
        return new TableQuery($body instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection ? new \SqlSemantics\Platform\MySql\Statement\Query\With\DeferredBody($body) : $body, $duplicate, as: str_starts_with($as->signature, 'as_create_query_expression: AS '));
    }

    /**
     * Publishes the complete request, including incompatible MERGE/query roles.
     *
     * @param list<bool>|null $optionsCommas Retained boundaries between options
     * @param list<TableElement> $elements Complete column and key definitions
     * @param list<\SqlSemantics\Platform\MySql\Statement\Table\TableOption> $options Complete table options
     * @param int $end The byte offset where the written definition ends
     *
     * @throws \SqlSemantics\Construction\RejectedInput When the server executes the written definition only to refuse it
     */
    public function create(QualifiedName $name, array $elements, array $options, ?Partitioning $partitioning, ?TableQuery $query, int $temporary, bool $exists, int $end, bool $enclosed = false, ?array $optionsCommas = null): CreateTable
    {
        $expression = $this->forbiddenPartitionExpression($partitioning);
        if ($expression !== null) {
            throw new \SqlSemantics\Construction\RejectedInput(new \SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\RefusedCreateTail($name, $elements, $options, $temporary, $exists, new \SqlSemantics\Platform\MySql\Statement\Partition\Problem\InvalidPartitionExpression($expression)), $end, sources: $this->lowering->origins->publish());
        }
        $problem = (new \SqlSemantics\Platform\MySql\Rules\TableDefinition\TableProblems())->problem($elements, $query, $this->lowering->profile->grammar);
        if ($problem !== null) {
            $definition = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Definition\DeferredCreateTable($name, $elements, $options, $partitioning, $query, $temporary, $exists, $enclosed, optionsCommas: $optionsCommas);

            throw new \SqlSemantics\Construction\RejectedInput(new \SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\RefusedCreateTable($definition, $problem), $end);
        }

        return new CreateTable($name, $elements, $options, $partitioning, $query, $temporary, $exists, $enclosed, optionsCommas: $optionsCommas);
    }

    /**
     * Finds the partitioning or subpartitioning expression the parser refuses in the selected
     * release: the server reports it while reading the statement, before any written-definition
     * problem and even inside a stored program body (verified on 8.4.7).
     */
    public function forbiddenPartitionExpression(?Partitioning $partitioning): ?\SqlSemantics\Statement\Scalar
    {
        if (!$partitioning instanceof \SqlSemantics\Platform\MySql\Statement\Partition\PartitionClause) {
            return null;
        }
        $rules = new \SqlSemantics\Platform\MySql\Rules\TableDefinition\PartitionExpressions();
        foreach ([$partitioning->method, $partitioning->subpartitioning?->method] as $method) {
            if (($method instanceof \SqlSemantics\Platform\MySql\Statement\Partition\Method\ExpressionMethod || $method instanceof \SqlSemantics\Platform\MySql\Statement\Partition\Method\HashMethod) && $rules->forbidden($method->expression, $this->lowering->profile->grammar)) {
                return $method->expression;
            }
        }

        return null;
    }
}
