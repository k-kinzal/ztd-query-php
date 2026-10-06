<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Invocation;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Syntax\KeywordCall;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Syntax\KeywordFunction;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Text\Extract;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Text\ExtractUnit;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Text\Overlay;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Text\Position;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Text\SimilarSubstring;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Text\Substring;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Text\Trim;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Text\TrimSide;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers EXTRACT, OVERLAY, POSITION, SUBSTRING and TRIM with their keyword-separated operands.
 *
 * Rule: PG-TEXT-LOWERING-001. Scope: the EXTRACT, OVERLAY, POSITION,
 * SUBSTRING and TRIM productions of `func_expr_common_subexpr`,
 * `extract_list`, `extract_arg`, `overlay_list`, `position_list`,
 * `substr_list`, `trim_list`. Constructors: `Extract`, `Overlay`,
 * `Position`, `Substring`, `SimilarSubstring`, `Trim`, and `KeywordCall`
 * for SUBSTRING and OVERLAY with plain arguments. BOTH in TRIM, and FROM in
 * TRIM without characters, are the defaults and noise (see
 * `InvocationNoise`). Termination: lists are flattened iteratively.
 * Source: https://www.postgresql.org/docs/17/functions-string.html,
 * https://www.postgresql.org/docs/17/functions-datetime.html#FUNCTIONS-DATETIME-EXTRACT. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql\Lowering
 */
final class TextRule
{
    /**
     * The trimmed ends by production signature.
     */
    private const SIDES = [
        'func_expr_common_subexpr: TRIM ( BOTH trim_list )' => TrimSide::Both,
        'func_expr_common_subexpr: TRIM ( LEADING trim_list )' => TrimSide::Leading,
        'func_expr_common_subexpr: TRIM ( TRAILING trim_list )' => TrimSide::Trailing,
        'func_expr_common_subexpr: TRIM ( trim_list )' => TrimSide::Both,
    ];

    /**
     * The EXTRACT field keywords by production signature.
     */
    private const UNITS = [
        'extract_arg: YEAR_P' => ExtractUnit::Year,
        'extract_arg: MONTH_P' => ExtractUnit::Month,
        'extract_arg: DAY_P' => ExtractUnit::Day,
        'extract_arg: HOUR_P' => ExtractUnit::Hour,
        'extract_arg: MINUTE_P' => ExtractUnit::Minute,
        'extract_arg: SECOND_P' => ExtractUnit::Second,
    ];

    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers an EXTRACT, OVERLAY, POSITION, SUBSTRING or TRIM production of `func_expr_common_subexpr`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function expression(Form $form): Scalar
    {
        if (isset(self::SIDES[$form->signature])) {
            return $this->trim(self::SIDES[$form->signature], $form->node(count($form->node->children) - 2));
        }

        return match ($form->signature) {
            'func_expr_common_subexpr: EXTRACT ( extract_list )' => $this->extract($form->node(2)),
            'func_expr_common_subexpr: OVERLAY ( overlay_list )' => $this->overlay($form->node(2)),
            'func_expr_common_subexpr: OVERLAY ( func_arg_list_opt )' => new KeywordCall(KeywordFunction::Overlay, $this->lowering->invocations->arguments($form->node(2))),
            'func_expr_common_subexpr: POSITION ( position_list )' => $this->position($form->node(2)),
            'func_expr_common_subexpr: SUBSTRING ( substr_list )' => $this->substring($form->node(2)),
            'func_expr_common_subexpr: SUBSTRING ( func_arg_list_opt )' => new KeywordCall(KeywordFunction::Substring, $this->lowering->invocations->arguments($form->node(2))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `extract_list` with its `extract_arg`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function extract(Node $list): Extract
    {
        $form = $this->lowering->productions->form($list);
        if ($form->signature !== 'extract_list: extract_arg FROM a_expr') {
            throw ImplementationGap::production($form);
        }
        $argument = $this->lowering->productions->form($form->node(0));
        $field = self::UNITS[$argument->signature] ?? match ($argument->signature) {
            'extract_arg: IDENT' => $this->lowering->names->token($argument->token(0)),
            'extract_arg: Sconst' => $this->lowering->literals->string($argument->node(0)),
            default => throw ImplementationGap::production($argument),
        };

        return new Extract($field, $this->lowering->expressions->expression($form->node(2)));
    }

    /**
     * Lowers `overlay_list`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function overlay(Node $list): Overlay
    {
        $form = $this->lowering->productions->form($list);
        $expressions = $this->lowering->expressions;
        $count = match ($form->signature) {
            'overlay_list: a_expr PLACING a_expr FROM a_expr FOR a_expr' => $form->node(6),
            'overlay_list: a_expr PLACING a_expr FROM a_expr' => null,
            default => throw ImplementationGap::production($form),
        };

        return new Overlay($expressions->expression($form->node(0)), $expressions->expression($form->node(2)), $expressions->expression($form->node(4)), $count === null ? null : $expressions->expression($count));
    }

    /**
     * Lowers `position_list`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function position(Node $list): Position
    {
        $form = $this->lowering->productions->form($list);
        if ($form->signature !== 'position_list: b_expr IN_P b_expr') {
            throw ImplementationGap::production($form);
        }

        return new Position($this->lowering->expressions->expression($form->node(0)), $this->lowering->expressions->expression($form->node(2)));
    }

    /**
     * Lowers `substr_list`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function substring(Node $list): Scalar
    {
        $form = $this->lowering->productions->form($list);
        $e = fn (int $position): Scalar => $this->lowering->expressions->expression($form->node($position));

        return match ($form->signature) {
            'substr_list: a_expr FROM a_expr FOR a_expr' => new Substring($e(0), $e(2), $e(4)),
            'substr_list: a_expr FOR a_expr FROM a_expr' => new Substring($e(0), $e(4), $e(2), true),
            'substr_list: a_expr FROM a_expr' => new Substring($e(0), $e(2)),
            'substr_list: a_expr FOR a_expr' => new Substring($e(0), null, $e(2)),
            'substr_list: a_expr SIMILAR a_expr ESCAPE a_expr' => new SimilarSubstring($e(0), $e(2), $e(4)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `trim_list` for a side.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function trim(TrimSide $side, Node $list): Trim
    {
        $form = $this->lowering->productions->form($list);
        $expressions = $this->lowering->expressions;

        return match ($form->signature) {
            'trim_list: a_expr FROM expr_list' => new Trim($side, $expressions->expression($form->node(0)), $expressions->expressions($form->node(2))),
            'trim_list: FROM expr_list' => new Trim($side, null, $expressions->expressions($form->node(1))),
            'trim_list: expr_list' => new Trim($side, null, $expressions->expressions($form->node(0))),
            default => throw ImplementationGap::production($form),
        };
    }
}
