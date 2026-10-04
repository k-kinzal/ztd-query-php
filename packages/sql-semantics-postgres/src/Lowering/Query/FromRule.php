<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Query;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\DerivedTable;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\FunctionTable;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\Join;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\JoinKind;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\JoinOn;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\JoinUsing;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\ParenthesizedJoin;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\RelationList;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\TableFunction;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\TableInput;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\TableSample;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypedColumn;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Relation;

/**
 * Lowers FROM clauses and their items.
 *
 * Rule: PG-FROM-LOWER-001. Scope: `from_clause`, `from_list`, `table_ref`,
 * `joined_table`, `join_type`, `opt_outer`, `join_qual`, `alias_clause`,
 * `opt_alias_clause`, `opt_alias_clause_for_join_using`, `func_alias_clause`,
 * `relation_expr`, `extended_relation_expr`, `relation_expr_list`,
 * `tablesample_clause`, `opt_repeatable_clause`, `func_table`,
 * `rowsfrom_list`, `rowsfrom_item`, `opt_col_def_list`, `opt_ordinality`.
 * Constructors: `TableInput`, `RelationReference`, `TableSample`,
 * `DerivedTable`, `FunctionTable`, `TableFunction`, `Join`, `JoinOn`,
 * `JoinUsing`, `ParenthesizedJoin`, `RelationList`. Several comma-separated
 * items become a `RelationList`; one item stays itself. INNER and OUTER are
 * noise words. Termination: lists are flattened iteratively; joins recurse
 * on the tree depth.
 * Source: https://www.postgresql.org/docs/17/sql-select.html#SQL-FROM. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class FromRule
{
    /**
     * The kind of each `join_type` production.
     */
    private const KINDS = [
        'join_type: FULL opt_outer' => JoinKind::Full,
        'join_type: LEFT opt_outer' => JoinKind::Left,
        'join_type: RIGHT opt_outer' => JoinKind::Right,
        'join_type: INNER_P' => JoinKind::Inner,
    ];

    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers `from_clause` to its one item or the list of its items; no clause is null.
     */
    public function item(Node $clause): ?Relation
    {
        $items = $this->items($clause);

        return match (count($items)) {
            0 => null,
            1 => $items[0],
            default => new RelationList($items),
        };
    }

    /**
     * Lowers `from_clause` or `from_list`; no clause is an empty list.
     *
     * @return list<Relation>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function items(Node $clause): array
    {
        $form = $this->lowering->productions->form($clause);
        $list = match ($form->signature) {
            'from_clause:' => null,
            'from_clause: FROM from_list' => $form->node(1),
            'from_list: table_ref', 'from_list: from_list , table_ref' => $clause,
            default => throw ImplementationGap::production($form),
        };
        $items = [];
        foreach ($list === null ? [] : $this->lowering->items($list, 'from_list: table_ref', 'from_list: from_list , table_ref') as $reference) {
            $items[] = $this->reference($reference);
        }

        return $items;
    }

    /**
     * Lowers `table_ref`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function reference(Node $reference): Relation
    {
        $form = $this->lowering->productions->form($reference);
        $functions = new TableFunctionRule($this->lowering);

        return match ($form->signature) {
            'table_ref: relation_expr opt_alias_clause' => $this->table($form, null),
            'table_ref: relation_expr opt_alias_clause tablesample_clause' => $this->table($form, $this->sample($form->node(2))),
            'table_ref: func_table func_alias_clause' => $this->functions($form->node(0), $form->node(1), false),
            'table_ref: LATERAL_P func_table func_alias_clause' => $this->functions($form->node(1), $form->node(2), true),
            'table_ref: xmltable opt_alias_clause' => $functions->xml($form->node(0), $this->alias($form->node(1)), false),
            'table_ref: LATERAL_P xmltable opt_alias_clause' => $functions->xml($form->node(1), $this->alias($form->node(2)), true),
            'table_ref: json_table opt_alias_clause' => $functions->json($form->node(0), $this->alias($form->node(1)), false),
            'table_ref: LATERAL_P json_table opt_alias_clause' => $functions->json($form->node(1), $this->alias($form->node(2)), true),
            'table_ref: select_with_parens opt_alias_clause' => $this->derived($form->node(0), $form->node(1), false),
            'table_ref: LATERAL_P select_with_parens opt_alias_clause' => $this->derived($form->node(1), $form->node(2), true),
            'table_ref: joined_table' => $this->joined($form->node(0)),
            'table_ref: ( joined_table ) alias_clause' => $this->renamedJoin($form),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a `table_ref` of a relation with its alias and sample.
     */
    public function table(Form $form, ?TableSample $sample): TableInput
    {
        [$alias, $columns] = $this->alias($form->node(1));

        return new TableInput($this->relation($form->node(0)), $alias, $columns, $sample);
    }

    /**
     * Lowers a subquery in FROM with its alias.
     */
    public function derived(Node $query, Node $alias, bool $lateral): DerivedTable
    {
        $subquery = (new SelectRule($this->lowering))->withParens($query);
        [$name, $columns] = $this->alias($alias);

        return new DerivedTable($subquery, $name, $columns, $lateral);
    }

    /**
     * Lowers `( joined_table ) alias_clause`.
     *
     * @throws ImplementationGap When the alias is not lowered to a name
     */
    public function renamedJoin(Form $form): ParenthesizedJoin
    {
        $join = $this->joined($form->node(1));
        [$alias, $columns] = $this->alias($form->node(3));
        if ($alias === null) {
            throw ImplementationGap::production($form);
        }

        return new ParenthesizedJoin($join, $alias, $columns);
    }

    /**
     * Lowers `joined_table`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function joined(Node $join): Join|ParenthesizedJoin
    {
        $form = $this->lowering->productions->form($join);

        return match ($form->signature) {
            'joined_table: ( joined_table )' => new ParenthesizedJoin($this->joined($form->node(1))),
            'joined_table: table_ref CROSS JOIN table_ref' => new Join($this->reference($form->node(0)), JoinKind::Cross, $this->reference($form->node(3))),
            'joined_table: table_ref join_type JOIN table_ref join_qual' => new Join($this->reference($form->node(0)), $this->kind($form->node(1)), $this->reference($form->node(3)), $this->qualification($form->node(4))),
            'joined_table: table_ref JOIN table_ref join_qual' => new Join($this->reference($form->node(0)), JoinKind::Inner, $this->reference($form->node(2)), $this->qualification($form->node(3))),
            'joined_table: table_ref NATURAL join_type JOIN table_ref' => new Join($this->reference($form->node(0)), $this->kind($form->node(2)), $this->reference($form->node(4)), null, true),
            'joined_table: table_ref NATURAL JOIN table_ref' => new Join($this->reference($form->node(0)), JoinKind::Inner, $this->reference($form->node(3)), null, true),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `join_type`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function kind(Node $type): JoinKind
    {
        $form = $this->lowering->productions->form($type);
        $kind = self::KINDS[$form->signature] ?? throw ImplementationGap::production($form);
        if ($kind !== JoinKind::Inner) {
            $outer = $this->lowering->productions->form($form->node(1));
            if ($outer->signature !== 'opt_outer: OUTER_P' && $outer->signature !== 'opt_outer:') {
                throw ImplementationGap::production($outer);
            }
        }

        return $kind;
    }

    /**
     * Lowers `join_qual`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function qualification(Node $qualification): JoinOn|JoinUsing
    {
        $form = $this->lowering->productions->form($qualification);
        if ($form->signature === 'join_qual: ON a_expr') {
            return new JoinOn($this->lowering->expressions->expression($form->node(1)));
        }
        if ($form->signature !== 'join_qual: USING ( name_list ) opt_alias_clause_for_join_using') {
            throw ImplementationGap::production($form);
        }
        $alias = $this->lowering->productions->form($form->node(4));

        return new JoinUsing($this->lowering->names->names($form->node(2)), match ($alias->signature) {
            'opt_alias_clause_for_join_using: AS ColId' => $this->lowering->names->name($alias->node(1)),
            'opt_alias_clause_for_join_using:' => null,
            default => throw ImplementationGap::production($alias),
        });
    }

    /**
     * Lowers `opt_alias_clause` or `alias_clause`: the correlation name and the column names; no alias has neither.
     *
     * @return array{Name|null, list<Name>}
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function alias(Node $alias): array
    {
        $form = $this->lowering->productions->form($alias);
        $names = $this->lowering->names;

        return match ($form->signature) {
            'opt_alias_clause:' => [null, []],
            'opt_alias_clause: alias_clause' => $this->alias($form->node(0)),
            'alias_clause: AS ColId ( name_list )' => [$names->name($form->node(1)), $names->names($form->node(3))],
            'alias_clause: AS ColId' => [$names->name($form->node(1)), []],
            'alias_clause: ColId ( name_list )' => [$names->name($form->node(0)), $names->names($form->node(2))],
            'alias_clause: ColId' => [$names->name($form->node(0)), []],
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `func_table` with its `func_alias_clause`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function functions(Node $table, Node $alias, bool $lateral): FunctionTable
    {
        $form = $this->lowering->productions->form($table);
        [$name, $columns, $definitions] = $this->functionAlias($alias);

        return match ($form->signature) {
            'func_table: func_expr_windowless opt_ordinality' => new FunctionTable([new TableFunction($this->lowering->invocations->call($form->node(0)))], false, $this->ordinality($form->node(1)), $name, $columns, $definitions, $lateral),
            'func_table: ROWS FROM ( rowsfrom_list ) opt_ordinality' => new FunctionTable($this->rowsFrom($form->node(3)), true, $this->ordinality($form->node(5)), $name, $columns, $definitions, $lateral),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `rowsfrom_list`.
     *
     * @return list<TableFunction>
     *
     * @throws ImplementationGap When an item has no rule
     */
    public function rowsFrom(Node $list): array
    {
        $functions = [];
        foreach ($this->lowering->items($list, 'rowsfrom_list: rowsfrom_item', 'rowsfrom_list: rowsfrom_list , rowsfrom_item') as $item) {
            $form = $this->lowering->productions->form($item);
            if ($form->signature !== 'rowsfrom_item: func_expr_windowless opt_col_def_list') {
                throw ImplementationGap::production($form);
            }
            $call = $this->lowering->invocations->call($form->node(0));
            $definitions = $this->lowering->productions->form($form->node(1));
            $functions[] = new TableFunction($call, match ($definitions->signature) {
                'opt_col_def_list: AS ( TableFuncElementList )' => $this->lowering->types->typedColumns($definitions->node(2)),
                'opt_col_def_list:' => [],
                default => throw ImplementationGap::production($definitions),
            });
        }

        return $functions;
    }

    /**
     * Lowers `func_alias_clause`: the correlation name, the column names and the column definitions.
     *
     * @return array{Name|null, list<Name>, list<TypedColumn>}
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function functionAlias(Node $alias): array
    {
        $form = $this->lowering->productions->form($alias);
        $types = $this->lowering->types;

        return match ($form->signature) {
            'func_alias_clause: alias_clause' => [...$this->alias($form->node(0)), []],
            'func_alias_clause: AS ( TableFuncElementList )' => [null, [], $types->typedColumns($form->node(2))],
            'func_alias_clause: AS ColId ( TableFuncElementList )' => [$this->lowering->names->name($form->node(1)), [], $types->typedColumns($form->node(3))],
            'func_alias_clause: ColId ( TableFuncElementList )' => [$this->lowering->names->name($form->node(0)), [], $types->typedColumns($form->node(2))],
            'func_alias_clause:' => [null, [], []],
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `opt_ordinality`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function ordinality(Node $ordinality): bool
    {
        $form = $this->lowering->productions->form($ordinality);

        return match ($form->signature) {
            'opt_ordinality: WITH_LA ORDINALITY' => true,
            'opt_ordinality:' => false,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `relation_expr` or `extended_relation_expr`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function relation(Node $relation): RelationReference
    {
        $form = $this->lowering->productions->form($relation);
        $names = $this->lowering->names;

        return match ($form->signature) {
            'relation_expr: qualified_name' => new RelationReference($names->qualified($form->node(0))),
            'relation_expr: extended_relation_expr' => $this->relation($form->node(0)),
            'extended_relation_expr: qualified_name *' => new RelationReference($names->qualified($form->node(0))),
            'extended_relation_expr: ONLY qualified_name' => new RelationReference($names->qualified($form->node(1)), true),
            'extended_relation_expr: ONLY ( qualified_name )' => new RelationReference($names->qualified($form->node(2)), true),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `relation_expr_list`.
     *
     * @return list<RelationReference>
     */
    public function relations(Node $list): array
    {
        $relations = [];
        foreach ($this->lowering->items($list, 'relation_expr_list: relation_expr', 'relation_expr_list: relation_expr_list , relation_expr') as $relation) {
            $relations[] = $this->relation($relation);
        }

        return $relations;
    }

    /**
     * Lowers `tablesample_clause`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function sample(Node $clause): TableSample
    {
        $form = $this->lowering->productions->form($clause);
        if ($form->signature !== 'tablesample_clause: TABLESAMPLE func_name ( expr_list ) opt_repeatable_clause') {
            throw ImplementationGap::production($form);
        }
        $repeatable = $this->lowering->productions->form($form->node(5));

        return new TableSample($this->lowering->names->dotted($form->node(1)), $this->lowering->expressions->expressions($form->node(3)), match ($repeatable->signature) {
            'opt_repeatable_clause: REPEATABLE ( a_expr )' => $this->lowering->expressions->expression($repeatable->node(2)),
            'opt_repeatable_clause:' => null,
            default => throw ImplementationGap::production($repeatable),
        });
    }
}
