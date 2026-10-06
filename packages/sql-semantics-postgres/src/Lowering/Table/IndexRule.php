<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Table;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Index\ColumnKey;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Index\CreateIndex;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Index\ExpressionKey;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Index\IndexElement;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers CREATE INDEX and the index keys several statements share.
 *
 * Rule: PG-INDEX-LOWER-001. Scope: `IndexStmt`, `opt_unique`,
 * `access_method_clause`, `index_params`, `index_elem`,
 * `index_elem_options`, `opt_include`, `index_including_params`,
 * `OptTableSpace`, `OptWhereClause`. Constructors: `CreateIndex`,
 * `IndexElement`, `ColumnKey`, `ExpressionKey`. A key written as a name is a
 * `ColumnKey`; a function call or a parenthesized expression is an
 * `ExpressionKey` that keeps whether the parentheses are written.
 * Termination: lists are flattened iteratively. Source:
 * https://www.postgresql.org/docs/17/sql-createindex.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class IndexRule
{
    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers `IndexStmt`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function statement(Node $statement): CreateIndex
    {
        $form = $this->lowering->productions->form($statement);
        $at = match ($form->signature) {
            'IndexStmt: CREATE opt_unique INDEX opt_concurrently opt_single_name ON relation_expr access_method_clause ( index_params ) opt_include opt_unique_null_treatment opt_reloptions OptTableSpace where_clause' => 0,
            'IndexStmt: CREATE opt_unique INDEX opt_concurrently IF_P NOT EXISTS name ON relation_expr access_method_clause ( index_params ) opt_include opt_unique_null_treatment opt_reloptions OptTableSpace where_clause' => 3,
            default => throw ImplementationGap::production($form),
        };
        $names = $this->lowering->names;

        return new CreateIndex(
            $at === 0 ? $names->optional($form->node(4)) : $names->name($form->node(7)),
            $this->lowering->queries->relation($form->node(6 + $at)),
            $this->indexParameters($form->node(9 + $at)),
            $this->unique($form->node(1)),
            $this->lowering->flags->present($form->node(3)),
            $at !== 0,
            $this->method($form->node(7 + $at)),
            $this->included($form->node(11 + $at)),
            $this->lowering->tables->uniqueNullTreatment($form->node(12 + $at)),
            $this->lowering->options->definitions($form->node(13 + $at)),
            $this->tablespace($form->node(14 + $at)),
            $this->lowering->queries->where($form->node(15 + $at)),
        );
    }

    /**
     * Lowers `opt_unique`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function unique(Node $flag): bool
    {
        $form = $this->lowering->productions->form($flag);

        return match ($form->signature) {
            'opt_unique: UNIQUE' => true,
            'opt_unique:' => false,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `access_method_clause`; none is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function method(Node $clause): ?Name
    {
        $form = $this->lowering->productions->form($clause);

        return match ($form->signature) {
            'access_method_clause: USING name' => $this->lowering->names->name($form->node(1)),
            'access_method_clause:' => null,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `OptTableSpace`; none is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function tablespace(Node $clause): ?Name
    {
        $form = $this->lowering->productions->form($clause);

        return match ($form->signature) {
            'OptTableSpace: TABLESPACE name' => $this->lowering->names->name($form->node(1)),
            'OptTableSpace:' => null,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `OptWhereClause`; none is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function predicate(Node $clause): ?Scalar
    {
        $form = $this->lowering->productions->form($clause);

        return match ($form->signature) {
            'OptWhereClause: WHERE ( a_expr )' => $this->lowering->expressions->expression($form->node(2)),
            'OptWhereClause:' => null,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `index_params`.
     *
     * @return list<IndexElement>
     */
    public function indexParameters(Node $list): array
    {
        $elements = [];
        foreach ($this->lowering->items($list, 'index_params: index_elem', 'index_params: index_params , index_elem') as $item) {
            $elements[] = $this->element($item);
        }

        return $elements;
    }

    /**
     * Lowers `opt_include`.
     *
     * @return list<IndexElement>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function included(Node $clause): array
    {
        $form = $this->lowering->productions->form($clause);
        if ($form->signature === 'opt_include:') {
            return [];
        }
        if ($form->signature !== 'opt_include: INCLUDE ( index_including_params )') {
            throw ImplementationGap::production($form);
        }
        $elements = [];
        foreach ($this->lowering->items($form->node(2), 'index_including_params: index_elem', 'index_including_params: index_including_params , index_elem') as $item) {
            $elements[] = $this->element($item);
        }

        return $elements;
    }

    /**
     * Lowers `index_elem` with its `index_elem_options`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function element(Node $element): IndexElement
    {
        $form = $this->lowering->productions->form($element);
        [$key, $options] = match ($form->signature) {
            'index_elem: ColId index_elem_options' => [new ColumnKey($this->lowering->names->name($form->node(0))), $form->node(1)],
            'index_elem: func_expr_windowless index_elem_options' => [new ExpressionKey($this->lowering->invocations->call($form->node(0)), false), $form->node(1)],
            'index_elem: ( a_expr ) index_elem_options' => [new ExpressionKey($this->lowering->expressions->expression($form->node(1))), $form->node(3)],
            default => throw ImplementationGap::production($form),
        };

        return $this->options($key, $this->lowering->productions->form($options));
    }

    /**
     * Lowers `index_elem_options` for a key.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function options(ColumnKey|ExpressionKey $key, Form $form): IndexElement
    {
        $names = $this->lowering->names;
        $queries = $this->lowering->queries;

        return match ($form->signature) {
            'index_elem_options: opt_collate opt_qualified_name opt_asc_desc opt_nulls_order' => new IndexElement(
                $key,
                $names->optionalDotted($form->node(0)),
                $names->optionalDotted($form->node(1)),
                [],
                $queries->sortDirection($form->node(2)),
                $queries->nullsOrder($form->node(3)),
            ),
            'index_elem_options: opt_collate any_name reloptions opt_asc_desc opt_nulls_order' => new IndexElement(
                $key,
                $names->optionalDotted($form->node(0)),
                $names->dotted($form->node(1)),
                $this->lowering->options->definitions($form->node(2)),
                $queries->sortDirection($form->node(3)),
                $queries->nullsOrder($form->node(4)),
            ),
            default => throw ImplementationGap::production($form),
        };
    }
}
