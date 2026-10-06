<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Lowering\Definition;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\Sqlite\Lowering\Lowering;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ConstraintName;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Table\ConstraintRun;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Table\ForeignKey;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Table\TableCheck;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Table\TableConstraint;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Table\TableOption;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Table\TablePrimaryKey;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Table\TableUnique;

/**
 * Lowers the table constraints and the table options of a table definition.
 *
 * Rule: SQLITE-TABLE-CONSTRAINT-LOWER-001. Scope: conslist_opt, conslist,
 * tconscomma, tcons, table_option_set, table_option. Constructors:
 * ConstraintRun, ConstraintName, TablePrimaryKey, TableUnique, TableCheck,
 * ForeignKey, TableOption. A written comma between two constraints ends a
 * run; a missing one continues it, so the place of every comma is kept, as
 * is a comma the grammar admits before the first table option. A string
 * literal among the key terms is lowered by SQLITE-KEY-TERM-LOWER-001.
 * Terminates: both lists are flattened iteratively.
 * Source: https://sqlite.org/syntax/table-constraint.html,
 * https://sqlite.org/syntax/table-options.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class TableConstraintRule
{
    private readonly ForeignKeyRule $foreignKeys;

    private readonly ColumnRule $columns;

    private readonly WordRule $words;

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
        $this->foreignKeys = new ForeignKeyRule($lowering);
        $this->columns = new ColumnRule($lowering);
        $this->words = new WordRule($lowering);
    }

    /**
     * Lowers the optional constraint list into comma-separated runs.
     *
     * @return list<ConstraintRun>
     * @throws ImplementationGap When a production has no rule
     */
    public function runs(Node $list): array
    {
        $form = $this->lowering->productions->form($list);
        if ($form->signature === 'conslist_opt:') {
            return [];
        }
        $head = $this->lowering->productions->form($form->signature === 'conslist_opt: COMMA conslist' ? $form->node(1) : throw ImplementationGap::production($form));
        if ($head->signature !== 'conslist: tcons' && $head->signature !== 'conslist: conslist tconscomma tcons') {
            throw ImplementationGap::production($head);
        }
        $runs = [];
        $items = [];
        foreach ((new Lists())->items($form->node(1)) as $item) {
            $element = $this->lowering->productions->form($item);
            if ($element->signature === 'tconscomma: COMMA') {
                $runs[] = new ConstraintRun($items);
                $items = [];
            } elseif ($element->signature !== 'tconscomma:') {
                $items[] = $this->constraint($element);
            }
        }
        $runs[] = new ConstraintRun($items);

        return $runs;
    }

    /**
     * Lowers one table constraint.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function constraint(Form $form): TableConstraint
    {
        return match ($form->signature) {
            'tcons: CONSTRAINT nm' => new ConstraintName($this->lowering->names->name($form->node(1))),
            'tcons: PRIMARY KEY LP sortlist autoinc RP onconf' => new TablePrimaryKey(
                (new KeyTermRule())->keyTerms($this->lowering->ordering->terms($form->node(3))),
                $this->lowering->conflicts->onConflict($form->node(6)),
                $this->columns->autoincrement($form->node(4)),
            ),
            'tcons: UNIQUE LP sortlist RP onconf' => new TableUnique((new KeyTermRule())->keyTerms($this->lowering->ordering->terms($form->node(2))), $this->lowering->conflicts->onConflict($form->node(4))),
            'tcons: CHECK LP expr RP onconf' => new TableCheck($this->lowering->expressions->expression($form->node(2)), $this->lowering->conflicts->onConflict($form->node(4))),
            'tcons: FOREIGN KEY LP eidlist RP REFERENCES nm eidlist_opt refargs defer_subclause_opt' => new ForeignKey(
                $this->lowering->ordering->columns($form->node(3)),
                $this->foreignKeys->clause($form->node(6), $form->node(7), $form->node(8)),
                $this->foreignKeys->optionalDeferrability($form->node(9)),
            ),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the table options in written order.
     *
     * @return list<TableOption>
     * @throws ImplementationGap When a production has no rule
     */
    public function options(Node $set): array
    {
        $form = $this->lowering->productions->form($set);
        if (!in_array($form->signature, ['table_option_set:', 'table_option_set: table_option', 'table_option_set: table_option_set COMMA table_option'], true)) {
            throw ImplementationGap::production($form);
        }
        $options = [];
        foreach ((new Lists())->elements($set) as $element) {
            if (!$element instanceof Token) {
                $options[] = $this->option($element);
            }
        }

        return $options;
    }

    /**
     * Tells whether a comma is written before the first table option, which the grammar admits after an empty option set.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function leadingComma(Node $set): bool
    {
        $form = $this->lowering->productions->form($set);
        if (!in_array($form->signature, ['table_option_set:', 'table_option_set: table_option', 'table_option_set: table_option_set COMMA table_option'], true)) {
            throw ImplementationGap::production($form);
        }
        $elements = (new Lists())->elements($set);

        return ($elements[0] ?? null) instanceof Token;
    }

    /**
     * Lowers one table option.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function option(Node $option): TableOption
    {
        $form = $this->lowering->productions->form($option);

        return match ($form->signature) {
            'table_option: WITHOUT nm' => new TableOption($this->words->name($form->node(1)), true),
            'table_option: nm' => new TableOption($this->words->name($form->node(0))),
            default => throw ImplementationGap::production($form),
        };
    }
}
