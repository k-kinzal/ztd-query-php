<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Invocation;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Cast;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\CastSpelling;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Conditional\Coalesce;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Conditional\MinMax;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Conditional\MinMaxKind;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Conditional\NullIf;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Syntax\CollationFor;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Syntax\MergeAction;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Syntax\Normalize;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Syntax\Treat;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Syntax\ValueFunction;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Syntax\ValueFunctionKind;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers the SQL-syntax functions of `func_expr_common_subexpr` and routes the textual, XML and JSON ones to their rules.
 *
 * Rule: PG-SYNTAX-LOWERING-001. Scope: `func_expr_common_subexpr`.
 * Constructors: `ValueFunction` (CURRENT_DATE … CURRENT_SCHEMA, SYSTEM_USER),
 * `CollationFor`, `Cast` of the expression family with the function
 * spelling, `Normalize`, `Treat`, `NullIf`, `Coalesce`, `MinMax`,
 * `MergeAction`; EXTRACT, OVERLAY, POSITION, SUBSTRING and TRIM go to
 * `TextRule`, the XML functions to `XmlRule`, the JSON functions to
 * `JsonRule` and `JsonQueryRule`. Termination: constant work per node.
 * Source: https://www.postgresql.org/docs/17/functions.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql\Lowering
 */
final class SyntaxRule
{
    /**
     * The functions written as a bare keyword, by production signature.
     */
    private const VALUES = [
        'func_expr_common_subexpr: CURRENT_DATE' => ValueFunctionKind::CurrentDate,
        'func_expr_common_subexpr: CURRENT_TIME' => ValueFunctionKind::CurrentTime,
        'func_expr_common_subexpr: CURRENT_TIMESTAMP' => ValueFunctionKind::CurrentTimestamp,
        'func_expr_common_subexpr: LOCALTIME' => ValueFunctionKind::Localtime,
        'func_expr_common_subexpr: LOCALTIMESTAMP' => ValueFunctionKind::Localtimestamp,
        'func_expr_common_subexpr: CURRENT_ROLE' => ValueFunctionKind::CurrentRole,
        'func_expr_common_subexpr: CURRENT_USER' => ValueFunctionKind::CurrentUser,
        'func_expr_common_subexpr: SESSION_USER' => ValueFunctionKind::SessionUser,
        'func_expr_common_subexpr: SYSTEM_USER' => ValueFunctionKind::SystemUser,
        'func_expr_common_subexpr: USER' => ValueFunctionKind::User,
        'func_expr_common_subexpr: CURRENT_CATALOG' => ValueFunctionKind::CurrentCatalog,
        'func_expr_common_subexpr: CURRENT_SCHEMA' => ValueFunctionKind::CurrentSchema,
    ];

    /**
     * The time functions with a precision, by production signature.
     */
    private const PRECISE = [
        'func_expr_common_subexpr: CURRENT_TIME ( Iconst )' => ValueFunctionKind::CurrentTime,
        'func_expr_common_subexpr: CURRENT_TIMESTAMP ( Iconst )' => ValueFunctionKind::CurrentTimestamp,
        'func_expr_common_subexpr: LOCALTIME ( Iconst )' => ValueFunctionKind::Localtime,
        'func_expr_common_subexpr: LOCALTIMESTAMP ( Iconst )' => ValueFunctionKind::Localtimestamp,
    ];

    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers `func_expr_common_subexpr`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function subexpression(Node $subexpression): Scalar
    {
        $form = $this->lowering->productions->form($subexpression);
        if (isset(self::VALUES[$form->signature])) {
            return new ValueFunction(self::VALUES[$form->signature]);
        }
        if (isset(self::PRECISE[$form->signature])) {
            return new ValueFunction(self::PRECISE[$form->signature], $this->lowering->literals->integer($form->node(2)));
        }
        $keyword = $form->token(0)->name;

        return match (true) {
            str_starts_with($keyword, 'XML') => (new XmlRule($this->lowering))->expression($form),
            in_array($keyword, ['JSON_QUERY', 'JSON_EXISTS', 'JSON_VALUE'], true) => (new JsonQueryRule($this->lowering))->function($form),
            str_starts_with($keyword, 'JSON') => (new JsonRule($this->lowering))->creation($form),
            in_array($keyword, ['EXTRACT', 'OVERLAY', 'POSITION', 'SUBSTRING', 'TRIM'], true) => (new TextRule($this->lowering))->expression($form),
            default => $this->special($form),
        };
    }

    /**
     * Lowers the remaining SQL-syntax functions.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function special(Form $form): Scalar
    {
        $expressions = $this->lowering->expressions;

        return match ($form->signature) {
            'func_expr_common_subexpr: COLLATION FOR ( a_expr )' => new CollationFor($expressions->expression($form->node(3))),
            'func_expr_common_subexpr: CAST ( a_expr AS Typename )' => new Cast($expressions->expression($form->node(2)), $this->lowering->types->typeName($form->node(4)), CastSpelling::Function),
            'func_expr_common_subexpr: TREAT ( a_expr AS Typename )' => new Treat($expressions->expression($form->node(2)), $this->lowering->types->typeName($form->node(4))),
            'func_expr_common_subexpr: NORMALIZE ( a_expr )' => new Normalize($expressions->expression($form->node(2))),
            'func_expr_common_subexpr: NORMALIZE ( a_expr , unicode_normal_form )' => new Normalize($expressions->expression($form->node(2)), $this->lowering->flags->normalForm($form->node(4))),
            'func_expr_common_subexpr: NULLIF ( a_expr , a_expr )' => new NullIf($expressions->expression($form->node(2)), $expressions->expression($form->node(4))),
            'func_expr_common_subexpr: COALESCE ( expr_list )' => new Coalesce($expressions->expressions($form->node(2))),
            'func_expr_common_subexpr: GREATEST ( expr_list )' => new MinMax(MinMaxKind::Greatest, $expressions->expressions($form->node(2))),
            'func_expr_common_subexpr: LEAST ( expr_list )' => new MinMax(MinMaxKind::Least, $expressions->expressions($form->node(2))),
            'func_expr_common_subexpr: MERGE_ACTION ( )' => new MergeAction(),
            default => throw ImplementationGap::production($form),
        };
    }
}
