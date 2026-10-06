<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Lowering\Mutation;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\Sqlite\Lowering\Lowering;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Assignment;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Delete;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertDefaults;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertInto;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertRows;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertSelect;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\MutationTarget;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\RowAssignment;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Update;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Star;
use SqlSemantics\Platform\Sqlite\Statement\Query\TableStar;
use SqlSemantics\Platform\Sqlite\Statement\Query\ValuesClause;
use SqlSemantics\Platform\Sqlite\Statement\Query\With\WithClause;
use SqlSemantics\Platform\Sqlite\Statement\Relation\IndexChoice;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Statement;

/**
 * Lowers INSERT, UPDATE and DELETE.
 *
 * Rule: SQLITE-MUTATION-LOWER-001. Scope: the `cmd` productions of DELETE,
 * UPDATE and INSERT; insert_cmd, xfullname, setlist, where_opt_ret,
 * returning. An INSERT whose source is a bare VALUES clause is an insert of
 * written rows, any other source an insert of a query, DEFAULT VALUES an
 * insert of defaults. Assignments keep their written order and whether the
 * column is written as a parenthesised list. Terminates: the assignment list
 * is walked along its spine in a loop.
 * Source: https://sqlite.org/lang_insert.html, https://sqlite.org/lang_update.html,
 * https://sqlite.org/lang_delete.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class MutationRule
{
    private readonly UpsertRule $upserts;

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
        $this->upserts = new UpsertRule($lowering);
    }

    /**
     * Lowers a data change command, or answers null for another command.
     */
    public function command(Form $form): ?Statement
    {
        $lowering = $this->lowering;

        return match ($form->signature) {
            'cmd: with DELETE FROM xfullname indexed_opt where_opt_ret' => $this->delete($lowering->commonTables->optional($form->node(0)), $this->target($form->node(3), $lowering->inputs->optionalIndexed($form->node(4))), $form->node(5)),
            'cmd: with UPDATE orconf xfullname indexed_opt SET setlist from where_opt_ret' => $this->update($form),
            'cmd: with insert_cmd INTO xfullname idlist_opt select upsert' => $this->insert($this->into($form->node(1), $this->target($form->node(3)), $form->node(4), $lowering->commonTables->optional($form->node(0))), $form->node(5), $form->node(6)),
            'cmd: with insert_cmd INTO xfullname idlist_opt DEFAULT VALUES returning' => $this->defaults($form),
            default => null,
        };
    }

    /**
     * Lowers a DELETE from its parts.
     */
    public function delete(?WithClause $with, MutationTarget $target, Node $tail): Delete
    {
        [$where, $returning] = $this->whereReturning($tail);

        return new Delete($target, $where, $returning, $with);
    }

    /**
     * Lowers an UPDATE command.
     */
    public function update(Form $form): Update
    {
        $lowering = $this->lowering;
        $with = $lowering->commonTables->optional($form->node(0));
        $resolution = $lowering->conflicts->orConflict($form->node(2));
        $target = $this->target($form->node(3), $lowering->inputs->optionalIndexed($form->node(4)));
        $assignments = $this->assignments($form->node(6));
        $from = $lowering->inputs->from($form->node(7));
        [$where, $returning] = $this->whereReturning($form->node(8));

        return new Update($target, $assignments, $from, $where, $returning, $resolution, $with);
    }

    /**
     * Lowers the head of an INSERT.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function into(Node $verb, MutationTarget $target, Node $columns, ?WithClause $with): InsertInto
    {
        $form = $this->lowering->productions->form($verb);
        $list = $this->lowering->names->optionalList($columns) ?? [];

        return match ($form->signature) {
            'insert_cmd: INSERT orconf' => new InsertInto($target, $list, false, $this->lowering->conflicts->orConflict($form->node(1)), $with),
            'insert_cmd: REPLACE' => new InsertInto($target, $list, true, null, $with),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers an INSERT with a source query and its upsert tail.
     */
    public function insert(InsertInto $into, Node $select, Node $upsert): InsertRows|InsertSelect
    {
        $source = $this->lowering->selects->select($select);
        [$upserts, $returning] = $this->upserts->tail($upsert);

        return $source instanceof ValuesClause ? new InsertRows($into, $source, $upserts, $returning) : new InsertSelect($into, $source, $upserts, $returning);
    }

    /**
     * Lowers an INSERT of default values.
     */
    public function defaults(Form $form): InsertDefaults
    {
        $with = $this->lowering->commonTables->optional($form->node(0));
        $into = $this->into($form->node(1), $this->target($form->node(3)), $form->node(4), $with);

        return new InsertDefaults($into, $this->returning($form->node(7)));
    }

    /**
     * Lowers an `xfullname`: the written table with its optional correlation name.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function target(Node $name, ?IndexChoice $index = null): MutationTarget
    {
        $form = $this->lowering->productions->form($name);
        $names = $this->lowering->names;

        return match ($form->signature) {
            'xfullname: nm' => new MutationTarget(new QualifiedName($names->name($form->node(0))), null, $index),
            'xfullname: nm DOT nm' => new MutationTarget($names->pair($form->node(0), $form->node(2)), null, $index),
            'xfullname: nm DOT nm AS nm' => new MutationTarget($names->pair($form->node(0), $form->node(2)), $names->name($form->node(4)), $index),
            'xfullname: nm AS nm' => new MutationTarget(new QualifiedName($names->name($form->node(0))), $names->name($form->node(2)), $index),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a `setlist` into its assignments in written order.
     *
     * @return list<Assignment|RowAssignment>
     * @throws ImplementationGap When a production has no rule
     */
    public function assignments(Node $list): array
    {
        $forms = [];
        for ($node = $list; $node !== null;) {
            $form = $this->lowering->productions->form($node);
            $forms[] = $form;
            $node = match ($form->signature) {
                'setlist: setlist COMMA nm EQ expr', 'setlist: setlist COMMA LP idlist RP EQ expr' => $form->node(0),
                'setlist: nm EQ expr', 'setlist: LP idlist RP EQ expr' => null,
                default => throw ImplementationGap::production($form),
            };
        }
        $assignments = [];
        foreach (array_reverse($forms) as $form) {
            $last = count($form->node->children) - 1;
            if (str_ends_with($form->signature, 'LP idlist RP EQ expr')) {
                $columns = $this->lowering->names->list($form->node($last - 3));
                $assignments[] = new RowAssignment($columns, $this->lowering->expressions->expression($form->node($last)));
            } else {
                $column = $this->lowering->names->name($form->node($last - 2));
                $assignments[] = new Assignment($column, $this->lowering->expressions->expression($form->node($last)));
            }
        }

        return $assignments;
    }

    /**
     * Lowers a `where_opt_ret` into the predicate and the RETURNING columns.
     *
     * @return array{Scalar|null, list<ResultColumn|Star|TableStar>}
     * @throws ImplementationGap When the production has no rule
     */
    public function whereReturning(Node $tail): array
    {
        $form = $this->lowering->productions->form($tail);
        $expressions = $this->lowering->expressions;
        $results = $this->lowering->results;

        return match ($form->signature) {
            'where_opt_ret:' => [null, []],
            'where_opt_ret: WHERE expr' => [$expressions->expression($form->node(1)), []],
            'where_opt_ret: RETURNING selcollist' => [null, $results->columns($form->node(1))],
            'where_opt_ret: WHERE expr RETURNING selcollist' => [$expressions->expression($form->node(1)), $results->columns($form->node(3))],
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a `returning`: the RETURNING columns, or none when the clause is absent.
     *
     * @return list<ResultColumn|Star|TableStar>
     * @throws ImplementationGap When the production has no rule
     */
    public function returning(Node $returning): array
    {
        $form = $this->lowering->productions->form($returning);

        return match ($form->signature) {
            'returning:' => [],
            'returning: RETURNING selcollist' => $this->lowering->results->columns($form->node(1)),
            default => throw ImplementationGap::production($form),
        };
    }
}
