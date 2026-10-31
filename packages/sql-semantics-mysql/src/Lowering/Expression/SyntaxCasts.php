<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Expression;

use SqlParser\Parser\Node;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Rules\Expression\Conversion\CastProblems;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\NotSupportedYet;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\TooBigPrecision;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind;

/**
 * Interprets completed casts before a later input refusal.
 *
 * Target errors require the closing cast parenthesis; national character
 * warnings occur when the target type completes. Observed on native MySQL
 * 8.0.44, 8.4.7 and 9.1.0. Operand trees are not lowered or retained here.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SyntaxCasts
{
    /**
     * Answers the parse-time notice of a completed cast or target type.
     */
    public function completed(Node $node, Lowering $lowering): Deprecated|ParseFailure|null
    {
        if ($node->name === 'function_call_keyword') {
            return $this->json($node, $lowering);
        }
        if ($node->name === 'cast_type') {
            return $lowering->types->castTarget($node)->kind === CastKind::NationalChar && Deprecated::National->warnedIn($lowering->profile->grammar) ? Deprecated::National : null;
        }
        if ($node->name !== 'simple_expr') {
            return null;
        }
        $form = $lowering->productions->form($node);
        $aborts = !in_array($lowering->profile->grammar, [GrammarRelease::MySql5744, GrammarRelease::MySql8044, GrammarRelease::MySql810], true);
        if ($form->signature === 'simple_expr: CAST_SYM ( expr AT_SYM LOCAL_SYM AS cast_type opt_array_cast )') {
            return new ParseFailure(new NotSupportedYet('AT LOCAL'));
        }
        if ($form->signature === 'simple_expr: CAST_SYM ( expr AT_SYM TIME_SYM ZONE_SYM opt_interval TEXT_STRING_literal AS DATETIME_SYM type_datetime_precision )') {
            $precision = (int) ($lowering->types->precision($form->node(10)) ?? '0');

            return $precision > 6 ? new ParseFailure(new TooBigPrecision($precision, 'CAST'), $aborts) : null;
        }
        if (!in_array($form->signature, ['simple_expr: CAST_SYM ( expr AS cast_type )', 'simple_expr: CONVERT_SYM ( expr , cast_type )', 'simple_expr: CAST_SYM ( expr AS cast_type opt_array_cast )'], true)) {
            return null;
        }
        $array = $form->signature === 'simple_expr: CAST_SYM ( expr AS cast_type opt_array_cast )' && (new ConstructRule($lowering))->flag($form->node(5));
        $problem = $this->problem($form, $lowering, $array);

        return $problem === null ? null : new ParseFailure($problem, $aborts);
    }

    /**
     * Shares target bounds with construction while leaving operand spelling at
     * the input boundary. Decimal conditions carry only label byte offsets.
     */
    public function problem(\SqlSemantics\Lowering\Form $form, Lowering $lowering, bool $array, ?\SqlSemantics\Platform\MySql\Statement\Type\CastTarget $target = null): ?\SqlSemantics\Statement\Fact\Diagnostic
    {
        $target ??= $lowering->types->castTarget($form->node(4));
        $problem = (new CastProblems())->target($target, $array, $lowering->profile->grammar);
        if (!$problem instanceof \SqlSemantics\Platform\MySql\Statement\Type\Problem\InvalidTypeSize || $target->kind !== CastKind::Decimal || $problem->rule === \SqlSemantics\Platform\MySql\Statement\Type\Problem\TypeLimit::ScaleExceedsPrecision) {
            return $problem;
        }
        [$start, $end] = (new \SqlSemantics\Construction\SourceRanges())->span($form->node(2)) ?? [0, 0];
        return new \SqlSemantics\Platform\MySql\Statement\Type\Problem\CastSizeInput($problem, $start, $end);
    }

    /**
     * Retains a JSON_VALUE target's condition only after its entire call completes.
     * Unlike CAST precision refusals, this condition continues in every release.
     */
    public function json(Node $node, Lowering $lowering): ?ParseFailure
    {
        $form = $lowering->productions->form($node);
        if ($form->signature !== 'function_call_keyword: JSON_VALUE_SYM ( simple_expr , text_literal opt_returning_type opt_on_empty_or_error )') {
            return null;
        }
        $problem = (new \SqlSemantics\Platform\MySql\Lowering\Call\JsonRule($lowering))->problem($form);
        return $problem === null ? null : new ParseFailure($problem);
    }

}
