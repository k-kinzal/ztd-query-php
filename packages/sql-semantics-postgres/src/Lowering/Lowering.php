<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering;

use SqlParser\Parser\Node;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Lowering\Productions;
use SqlSemantics\Platform\PostgreSql\Lowering\Access\Privileges;
use SqlSemantics\Platform\PostgreSql\Lowering\Catalog\Catalogs;
use SqlSemantics\Platform\PostgreSql\Lowering\Expression\Expressions;
use SqlSemantics\Platform\PostgreSql\Lowering\Invocation\Invocations;
use SqlSemantics\Platform\PostgreSql\Lowering\Leaf\Flags;
use SqlSemantics\Platform\PostgreSql\Lowering\Leaf\Literals;
use SqlSemantics\Platform\PostgreSql\Lowering\Leaf\Names;
use SqlSemantics\Platform\PostgreSql\Lowering\Leaf\Operators;
use SqlSemantics\Platform\PostgreSql\Lowering\Leaf\Options;
use SqlSemantics\Platform\PostgreSql\Lowering\Leaf\Roles;
use SqlSemantics\Platform\PostgreSql\Lowering\Manipulation\Manipulations;
use SqlSemantics\Platform\PostgreSql\Lowering\Query\Queries;
use SqlSemantics\Platform\PostgreSql\Lowering\Routine\Routines;
use SqlSemantics\Platform\PostgreSql\Lowering\Table\Tables;
use SqlSemantics\Platform\PostgreSql\Lowering\Type\TypeNames;
use SqlSemantics\Platform\PostgreSql\Lowering\Utility\UtilityCommands;
use SqlSemantics\Statement\Statement;

/**
 * The entry point of PostgreSQL lowering: a parse tree in, statements out.
 *
 * Every family reaches the others through the entry objects held here; an
 * entry method is named after what it lowers and documents the nonterminals
 * it accepts. Rules hold the hub, never each other.
 *
 * Rule: PG-STATEMENT-001. Scope: `parse_toplevel`, `stmtmulti`,
 * `toplevel_stmt`, `stmt`. Each statement of the text becomes one statement;
 * an empty statement between semicolons requests nothing and produces none.
 * Termination: the statement list is flattened iteratively.
 * Source: https://www.postgresql.org/docs/17/sql-commands.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Lowering
{
    /**
     * Identifiers and names.
     */
    public readonly Names $names;

    /**
     * Role specifications.
     */
    public readonly Roles $roles;

    /**
     * Operator names.
     */
    public readonly Operators $operators;

    /**
     * Constants and parameters.
     */
    public readonly Literals $literals;

    /**
     * Definition lists, storage parameters, foreign-data options and configuration values.
     */
    public readonly Options $options;

    /**
     * Optional keywords shared by several families.
     */
    public readonly Flags $flags;

    /**
     * Type names.
     */
    public readonly TypeNames $types;

    /**
     * Scalar expressions.
     */
    public readonly Expressions $expressions;

    /**
     * Function calls and function-like expressions.
     */
    public readonly Invocations $invocations;

    /**
     * Queries and their clauses.
     */
    public readonly Queries $queries;

    /**
     * Data-modifying statements, prepared statements and cursors.
     */
    public readonly Manipulations $manipulations;

    /**
     * Table, sequence, index, view, trigger, rule and policy definitions.
     */
    public readonly Tables $tables;

    /**
     * Schemas, databases, extensions, foreign data, languages, publications, text search, domains, types and casts.
     */
    public readonly Catalogs $catalogs;

    /**
     * Routines, operators, operator classes and the generic object commands.
     */
    public readonly Routines $routines;

    /**
     * Roles and privileges.
     */
    public readonly Privileges $privileges;

    /**
     * Transactions, configuration, maintenance and the other utility commands.
     */
    public readonly UtilityCommands $utilities;

    /**
     * @param Productions $productions The productions of the grammar release
     * @param Leaves $leaves The record of operand leaves this lowering creates
     * @param GrammarRelease $release The grammar release, for rules that differ between releases
     */
    public function __construct(public readonly Productions $productions, public readonly Leaves $leaves, public readonly GrammarRelease $release)
    {
        $this->names = new Names($this);
        $this->roles = new Roles($this);
        $this->operators = new Operators($this);
        $this->literals = new Literals($this);
        $this->options = new Options($this);
        $this->flags = new Flags($this);
        $this->types = new TypeNames($this);
        $this->expressions = new Expressions($this);
        $this->invocations = new Invocations($this);
        $this->queries = new Queries($this);
        $this->manipulations = new Manipulations($this);
        $this->tables = new Tables($this);
        $this->catalogs = new Catalogs($this);
        $this->routines = new Routines($this);
        $this->privileges = new Privileges($this);
        $this->utilities = new UtilityCommands($this);
    }

    /**
     * Lowers a parse tree into the statements it requests, in order.
     *
     * @return list<Statement>
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function statements(Node $tree): array
    {
        $form = $this->productions->form($tree);
        if ($form->signature !== 'parse_toplevel: stmtmulti') {
            (new ProceduralModes())->reject($form);
        }
        $statements = [];
        foreach ($this->items($form->node(0), 'stmtmulti: toplevel_stmt', 'stmtmulti: stmtmulti ; toplevel_stmt') as $item) {
            $top = $this->productions->form($item);
            $statement = match ($top->signature) {
                'toplevel_stmt: stmt' => $this->optional($top->node(0)),
                'toplevel_stmt: TransactionStmtLegacy' => $this->utilities->statement($top->node(0)),
                default => throw ImplementationGap::production($top),
            };
            if ($statement !== null) {
                $statements[] = $statement;
            }
        }

        return $statements;
    }

    /**
     * Answers the items of a recursive list production in source order, without recursing on the list spine.
     *
     * Every node of the spine must match one of the given productions, so a
     * list rule claims exactly the list productions it names.
     *
     * @param string ...$spine The signatures of the list productions
     *
     * @return list<Node>
     *
     * @throws ImplementationGap When a node of the spine matches another production
     */
    public function items(Node $list, string ...$spine): array
    {
        $items = [];
        $pending = [$list];
        while ($pending !== []) {
            $current = array_pop($pending);
            if ($current->name !== $list->name) {
                $items[] = $current;
                continue;
            }
            $form = $this->productions->form($current);
            if (!in_array($form->signature, $spine, true)) {
                throw ImplementationGap::production($form);
            }
            for ($index = count($current->children) - 1; $index >= 0; $index--) {
                $child = $current->children[$index];
                if ($child instanceof Node) {
                    $pending[] = $child;
                }
            }
        }

        return $items;
    }

    /**
     * Lowers a `stmt` node; the empty statement produces nothing.
     */
    public function optional(Node $statement): ?Statement
    {
        $form = $this->productions->form($statement);

        return $form->signature === 'stmt:' ? null : $this->statement($form->node(0));
    }

    /**
     * Lowers a statement nonterminal, such as `SelectStmt` or `CreateStmt`, at the top level or nested in another statement.
     *
     * @throws ImplementationGap When the nonterminal is not a statement of the grammar
     */
    public function statement(Node $statement): Statement
    {
        $family = StatementRoutes::ROUTES['stmt: ' . $statement->name] ?? null;
        if ($family === null) {
            throw ImplementationGap::rule('statement ' . $statement->name);
        }

        return match ($family) {
            Family::Query => $this->queries->statement($statement),
            Family::Manipulation => $this->manipulations->statement($statement),
            Family::Table => $this->tables->statement($statement),
            Family::Catalog => $this->catalogs->statement($statement),
            Family::Routine => $this->routines->statement($statement),
            Family::Access => $this->privileges->statement($statement),
            Family::Utility => $this->utilities->statement($statement),
        };
    }
}
