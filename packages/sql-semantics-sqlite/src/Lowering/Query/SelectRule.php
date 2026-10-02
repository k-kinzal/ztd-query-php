<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Lowering\Query;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\Sqlite\Lowering\Lowering;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers query productions into selections.
 *
 * Rule: SQLITE-SELECT-LOWER-001. Scope: select, selectnowith, oneselect,
 * selcollist, sclp, as, from, seltablist, dbnm, where_opt. Result columns keep
 * their written order. Terminates: the result column list is walked along its
 * spine in a loop; every other child is a strict subtree.
 * Source: https://sqlite.org/lang_select.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class SelectRule
{
    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a complete query.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function select(Node $select): Select
    {
        $form = $this->lowering->productions->form($select);
        if ($form->signature !== 'select: selectnowith') {
            throw ImplementationGap::production($form);
        }
        $body = $this->lowering->productions->form($form->node(0));
        if ($body->signature !== 'selectnowith: oneselect') {
            throw ImplementationGap::production($body);
        }
        $one = $this->lowering->productions->form($body->node(0));
        if ($one->signature !== 'oneselect: SELECT distinct selcollist from where_opt groupby_opt having_opt orderby_opt limit_opt') {
            throw ImplementationGap::production($one);
        }
        foreach ([1 => 'distinct:', 5 => 'groupby_opt:', 6 => 'having_opt:', 7 => 'orderby_opt:', 8 => 'limit_opt:'] as $position => $empty) {
            $clause = $this->lowering->productions->form($one->node($position));
            if ($clause->signature !== $empty) {
                throw ImplementationGap::production($clause);
            }
        }

        return new Select($this->columns($one->node(2)), $this->from($one->node(3)), $this->where($one->node(4)));
    }

    /**
     * Lowers the result columns in written order.
     *
     * @return list<ResultColumn>
     * @throws ImplementationGap When a production has no rule
     */
    public function columns(Node $list): array
    {
        $nodes = [];
        for ($node = $list; $node !== null;) {
            $nodes[] = $node;
            $prefix = $this->lowering->productions->form($this->lowering->productions->form($node)->node(0));
            $node = $prefix->signature === 'sclp:' ? null : $prefix->node(0);
        }
        $columns = [];
        foreach (array_reverse($nodes) as $node) {
            $form = $this->lowering->productions->form($node);
            if ($form->signature !== 'selcollist: sclp scanpt expr scanpt as') {
                throw ImplementationGap::production($form);
            }
            $columns[] = new ResultColumn($this->lowering->expressions->expression($form->node(2)), $this->alias($form->node(4)));
        }

        return $columns;
    }

    /**
     * Lowers an optional alias.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function alias(Node $alias): ?Name
    {
        $form = $this->lowering->productions->form($alias);

        return match ($form->signature) {
            'as:' => null,
            'as: AS nm' => $this->lowering->names->name($form->node(1)),
            'as: ids' => $this->lowering->names->token($form->token(0)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the optional input relation.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function from(Node $from): ?TableInput
    {
        $form = $this->lowering->productions->form($from);
        if ($form->signature === 'from:') {
            return null;
        }
        $table = $this->lowering->productions->form($form->node(1));
        if ($table->signature !== 'seltablist: stl_prefix nm dbnm as on_using') {
            throw ImplementationGap::production($table);
        }
        foreach ([0 => 'stl_prefix:', 4 => 'on_using:'] as $position => $empty) {
            $part = $this->lowering->productions->form($table->node($position));
            if ($part->signature !== $empty) {
                throw ImplementationGap::production($part);
            }
        }
        $first = $this->lowering->names->name($table->node(1));
        $second = $this->lowering->productions->form($table->node(2));
        $name = $second->signature === 'dbnm:' ? new QualifiedName($first) : new QualifiedName($this->lowering->names->name($second->node(1)), $first);

        return new TableInput($name, $this->alias($table->node(3)));
    }

    /**
     * Lowers the optional row predicate.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function where(Node $where): ?Scalar
    {
        $form = $this->lowering->productions->form($where);

        return match ($form->signature) {
            'where_opt:' => null,
            'where_opt: WHERE expr' => $this->lowering->expressions->expression($form->node(1)),
            default => throw ImplementationGap::production($form),
        };
    }
}
