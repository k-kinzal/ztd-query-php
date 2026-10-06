<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Expression;

use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Expression\Access\DefaultOfColumn;
use SqlSemantics\Platform\MySql\Statement\Expression\Access\InsertedColumn;
use SqlSemantics\Platform\MySql\Statement\Expression\Access\JsonExtraction;
use SqlSemantics\Platform\MySql\Statement\Expression\Access\OdbcEscape;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\IntervalAddition;
use SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords;
use SqlSemantics\Platform\MySql\Statement\Expression\Row;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\Exists;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers the bracketed, subquery and column-function forms of simple_expr.
 *
 * Rule: MYSQL-SIMPLE-FORM-001. Scope: the simple_expr productions for a
 * grouping `( expr )`, a row `( expr , expr_list )` and `ROW ( … )` (the
 * keyword ROW is optional), a subquery `( subselect )` or row_subquery,
 * EXISTS, the ODBC escape `{ ident expr }`, `DEFAULT ( col )`,
 * `VALUES ( col )`, the leading `INTERVAL expr unit + expr` and the JSON
 * operators `->` and `->>`; every other production goes to
 * MYSQL-CONSTRUCT-001. Constructs: Grouped, Row, ScalarSubquery, Exists,
 * OdbcEscape, DefaultOfColumn, InsertedColumn, IntervalAddition,
 * JsonExtraction. Terminates: every child is a strict subtree.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/expressions.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class FormRule
{
    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers one of the forms.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function lower(Form $form): Scalar
    {
        $expressions = $this->lowering->expressions;
        $names = $this->lowering->names;
        $queries = $this->lowering->queries;

        return match ($form->signature) {
            'simple_expr: ( expr )' => new Grouped($expressions->expression($form->node(1))),
            'simple_expr: ( expr , expr_list )' => new Row([$expressions->expression($form->node(1)), ...$expressions->expressions($form->node(3))]),
            'simple_expr: ROW_SYM ( expr , expr_list )' => new Row([$expressions->expression($form->node(2)), ...$expressions->expressions($form->node(4))], OptionalWords::Written),
            'simple_expr: ( subselect )' => new ScalarSubquery($queries->query($form->node(1))),
            'simple_expr: row_subquery' => new ScalarSubquery($queries->query($form->node(0))),
            'simple_expr: EXISTS ( subselect )' => new Exists($queries->query($form->node(2))),
            'simple_expr: EXISTS table_subquery' => new Exists($queries->query($form->node(1))),
            'simple_expr: { ident expr }' => new OdbcEscape($names->identifier($form->node(1)), $expressions->expression($form->node(2))),
            'simple_expr: DEFAULT ( simple_ident )', 'simple_expr: DEFAULT_SYM ( simple_ident )' => new DefaultOfColumn($names->column($form->node(2))),
            'simple_expr: VALUES ( simple_ident_nospvar )' => new InsertedColumn($names->column($form->node(2))),
            'simple_expr: INTERVAL_SYM expr interval + expr' => new IntervalAddition($expressions->interval($form->node(1), $form->node(2)), $expressions->expression($form->node(4))),
            'simple_expr: simple_ident JSON_SEPARATOR_SYM TEXT_STRING_literal' => new JsonExtraction($names->column($form->node(0)), $this->lowering->literals->text($form->node(2))),
            'simple_expr: simple_ident JSON_UNQUOTED_SEPARATOR_SYM TEXT_STRING_literal' => new JsonExtraction($names->column($form->node(0)), $this->lowering->literals->text($form->node(2)), true),
            default => (new ConstructRule($this->lowering))->lower($form),
        };
    }
}
