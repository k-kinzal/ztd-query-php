<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Lowering\Definition;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\Sqlite\Lowering\Lowering;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ColumnDefinition;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTableAs;
use SqlSemantics\Statement\Statement;

/**
 * Lowers table definitions.
 *
 * Rule: SQLITE-CREATE-TABLE-LOWER-001. Scope: `cmd: create_table
 * create_table_args`, create_table, createkw, create_table_args, columnlist.
 * Constructors: CreateTable for a column list, CreateTableAs for a query.
 * Columns keep their written order. TEMP and IF NOT EXISTS are model values.
 * Terminates: the column list is walked along its spine in a loop.
 * Source: https://sqlite.org/lang_createtable.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class CreateTableRule
{
    private readonly ColumnRule $columns;

    private readonly TableConstraintRule $constraints;

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
        $this->columns = new ColumnRule($lowering);
        $this->constraints = new TableConstraintRule($lowering);
    }

    /**
     * Lowers a table definition, or answers null for a command of another family.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function command(Form $form): ?Statement
    {
        if ($form->signature !== 'cmd: create_table create_table_args') {
            return null;
        }
        $head = $this->lowering->productions->form($form->node(0));
        if ($head->signature !== 'create_table: createkw temp TABLE ifnotexists nm dbnm') {
            throw ImplementationGap::production($head);
        }
        $this->created($head->node(0));
        $temporary = $this->lowering->flags->temporary($head->node(1));
        $ifNotExists = $this->lowering->flags->ifNotExists($head->node(3));
        $name = $this->lowering->names->scoped($head->node(4), $head->node(5));
        $body = $this->lowering->productions->form($form->node(1));

        return match ($body->signature) {
            'create_table_args: LP columnlist conslist_opt RP table_option_set' => new CreateTable(
                $name,
                $this->columns($body->node(1)),
                $this->constraints->runs($body->node(2)),
                $this->constraints->options($body->node(4)),
                $temporary,
                $ifNotExists,
                $this->constraints->leadingComma($body->node(4)),
            ),
            'create_table_args: AS select' => new CreateTableAs($name, $this->lowering->selects->select($body->node(1)), $temporary, $ifNotExists),
            default => throw ImplementationGap::production($body),
        };
    }

    /**
     * Checks the CREATE keyword production that starts a definition.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function created(Node $keyword): void
    {
        $form = $this->lowering->productions->form($keyword);
        if ($form->signature !== 'createkw: CREATE') {
            throw ImplementationGap::production($form);
        }
    }

    /**
     * Lowers the column definitions in written order.
     *
     * @return list<ColumnDefinition>
     * @throws ImplementationGap When a production has no rule
     */
    public function columns(Node $list): array
    {
        $pairs = [];
        for ($node = $list; $node !== null;) {
            $form = $this->lowering->productions->form($node);
            [$name, $constraints, $node] = match ($form->signature) {
                'columnlist: columnname carglist' => [$form->node(0), $form->node(1), null],
                'columnlist: columnlist COMMA columnname carglist' => [$form->node(2), $form->node(3), $form->node(0)],
                default => throw ImplementationGap::production($form),
            };
            $pairs[] = [$name, $constraints];
        }
        $columns = [];
        foreach (array_reverse($pairs) as [$name, $constraints]) {
            $columns[] = $this->columns->column($name, $constraints);
        }

        return $columns;
    }
}
