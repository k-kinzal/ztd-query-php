<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Lowering\Definition;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\Sqlite\Lowering\Lowering;
use SqlSemantics\Platform\Sqlite\Statement\Schema\ColumnDefinition;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Lowers table definitions.
 *
 * Rule: SQLITE-CREATE-TABLE-LOWER-001. Scope: create_table, create_table_args,
 * columnlist, columnname, typetoken, typename, carglist, ccons. Columns keep
 * their written order. Terminates: the column list is walked along its spine
 * in a loop. Source: https://sqlite.org/lang_createtable.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
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
     * Lowers a table definition from its header and body.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function create(Node $header, Node $body): CreateTable
    {
        $productions = $this->lowering->productions;
        $head = $productions->form($header);
        $first = $this->lowering->names->name($head->node(4));
        $second = $productions->form($head->node(5));
        $name = $second->signature === 'dbnm:' ? new QualifiedName($first) : new QualifiedName($this->lowering->names->name($second->node(1)), $first);
        foreach ([1 => 'temp:', 3 => 'ifnotexists:'] as $position => $empty) {
            $part = $productions->form($head->node($position));
            if ($part->signature !== $empty) {
                throw ImplementationGap::production($part);
            }
        }
        $arguments = $productions->form($body);
        if ($arguments->signature !== 'create_table_args: LP columnlist conslist_opt RP table_option_set') {
            throw ImplementationGap::production($arguments);
        }
        foreach ([2 => 'conslist_opt:', 4 => 'table_option_set:'] as $position => $empty) {
            $part = $productions->form($arguments->node($position));
            if ($part->signature !== $empty) {
                throw ImplementationGap::production($part);
            }
        }

        return new CreateTable($name, $this->columns($arguments->node(1)));
    }

    /**
     * Lowers the column definitions in written order.
     *
     * @return list<ColumnDefinition>
     * @throws ImplementationGap When a production has no rule
     */
    public function columns(Node $list): array
    {
        $productions = $this->lowering->productions;
        $pairs = [];
        for ($node = $list; $node !== null;) {
            $form = $productions->form($node);
            if ($form->signature === 'columnlist: columnname carglist') {
                $pairs[] = [$form->node(0), $form->node(1)];
                $node = null;
            } else {
                $pairs[] = [$form->node(2), $form->node(3)];
                $node = $form->node(0);
            }
        }
        $columns = [];
        foreach (array_reverse($pairs) as [$column, $constraints]) {
            $named = $productions->form($column);
            $declared = $productions->form($named->node(1));
            $domain = match ($declared->signature) {
                'typetoken:' => null,
                'typetoken: typename' => $this->lowering->names->token($productions->form($declared->node(0))->token(0)),
                default => throw ImplementationGap::production($declared),
            };
            $columns[] = new ColumnDefinition($this->lowering->names->name($named->node(0)), $domain, $this->notNull($constraints));
        }

        return $columns;
    }

    /**
     * Lowers the column constraints this slice knows: NOT NULL or none.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function notNull(Node $constraints): bool
    {
        $productions = $this->lowering->productions;
        $form = $productions->form($constraints);
        if ($form->signature === 'carglist:') {
            return false;
        }
        $rest = $productions->form($form->node(0));
        $constraint = $productions->form($form->node(1));
        if ($rest->signature !== 'carglist:' || $constraint->signature !== 'ccons: NOT NULL onconf' || $productions->form($constraint->node(2))->signature !== 'onconf:') {
            throw ImplementationGap::production($constraint);
        }

        return true;
    }
}
