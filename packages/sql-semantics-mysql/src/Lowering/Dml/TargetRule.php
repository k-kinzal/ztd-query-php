<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Dml;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Dml\DuplicateHandling;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Lowers the table lists of multiple-table DELETE and locking clauses, and the duplicate handling of statements that copy rows.
 *
 * Rule: MYSQL-DML-TARGETS-001. Scope: table_alias_ref_list, table_alias_ref,
 * opt_duplicate, duplicate. A table of the list is a table name or a
 * correlation name with an optional database; the `.*` suffix names the
 * same table (LeafNoise). REPLACE and IGNORE are DuplicateHandling cases.
 * Terminates: lists are flattened by MYSQL-DML-LIST-001. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/delete.html,
 * https://dev.mysql.com/doc/refman/8.4/en/load-data.html,
 * https://dev.mysql.com/doc/refman/8.4/en/create-table-select.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Dml
 */
final class TargetRule
{
    /**
     * The duplicate handling by production.
     */
    private const DUPLICATES = [
        'opt_duplicate: REPLACE' => DuplicateHandling::Replace, 'opt_duplicate: IGNORE_SYM' => DuplicateHandling::Ignore,
        'duplicate: REPLACE_SYM' => DuplicateHandling::Replace, 'duplicate: IGNORE_SYM' => DuplicateHandling::Ignore,
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers the tables of a node of `table_alias_ref_list`.
     *
     * @return list<QualifiedName>
     * @throws ImplementationGap When a production has no rule
     */
    public function tables(Node $list): array
    {
        $items = (new ListRule($this->lowering))->items($list, [
            'table_alias_ref_list: table_alias_ref', 'table_alias_ref_list: table_alias_ref_list , table_alias_ref',
            'table_alias_ref_list: table_ident_opt_wild', 'table_alias_ref_list: table_alias_ref_list , table_ident_opt_wild',
        ]);
        $tables = [];
        foreach ($items as $item) {
            $form = $this->lowering->form($item);
            if ($form->signature === 'table_alias_ref: table_ident_opt_wild') {
                $item = $form->node(0);
            }
            $tables[] = $this->lowering->names->qualified($item);
        }

        return $tables;
    }

    /**
     * Lowers a node of `opt_duplicate` or `duplicate`; an absent keyword is null.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function duplicates(Node $duplicate): ?DuplicateHandling
    {
        $form = $this->lowering->form($duplicate);
        if ($form->signature === 'opt_duplicate:') {
            return null;
        }
        if ($form->signature === 'opt_duplicate: duplicate') {
            $form = $this->lowering->form($form->node(0));
        }

        return self::DUPLICATES[$form->signature] ?? throw ImplementationGap::production($form);
    }
}
