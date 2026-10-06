<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Call;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightCast;
use SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightLevel;
use SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightLevelRange;
use SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightString;
use SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightStringParameters;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Query\Direction;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers WEIGHT_STRING with its cast, its LEVEL clause of MySQL 5.6 and 5.7, and its internal form.
 *
 * Rule: MYSQL-CALL-WEIGHT-001. Scope: the WEIGHT_STRING alternatives of
 * function_call_conflict, ws_nweights, ws_num_codepoints, opt_ws_levels,
 * ws_level_list_or_range, ws_level_list, ws_level_list_item, ws_level_range,
 * ws_level_number, ws_level_flags, ws_level_flag_desc,
 * ws_level_flag_reverse. Constructs: WeightString, WeightStringParameters,
 * WeightLevel, WeightLevelRange. Terminates: the level list is flattened in
 * a loop; every other child is a strict subtree.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/string-functions.html#function_weight-string,
 * https://dev.mysql.com/doc/refman/5.7/en/string-functions.html#function_weight-string.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class WeightRule
{
    /**
     * The cast productions: the cast and the position of the length.
     */
    private const CASTS = [
        'function_call_conflict: WEIGHT_STRING_SYM ( expr AS CHAR_SYM ws_nweights opt_ws_levels )' => [WeightCast::Char, 5],
        'function_call_conflict: WEIGHT_STRING_SYM ( expr AS BINARY ws_nweights )' => [WeightCast::Binary, 5],
        'function_call_conflict: WEIGHT_STRING_SYM ( expr AS CHAR_SYM ws_num_codepoints )' => [WeightCast::Char, 5],
        'function_call_conflict: WEIGHT_STRING_SYM ( expr AS BINARY_SYM ws_num_codepoints )' => [WeightCast::Binary, 5],
    ];

    /**
     * The level flag productions: the direction and whether REVERSE is written.
     */
    private const FLAGS = [
        'ws_level_flags:' => [null, false], 'ws_level_flags: ws_level_flag_desc' => [true, false],
        'ws_level_flags: ws_level_flag_desc ws_level_flag_reverse' => [true, true], 'ws_level_flags: ws_level_flag_reverse' => [null, true],
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Tells whether a production is one of this rule.
     */
    public function claims(string $signature): bool
    {
        return isset(self::CASTS[$signature]) || in_array($signature, [
            'function_call_conflict: WEIGHT_STRING_SYM ( expr opt_ws_levels )', 'function_call_conflict: WEIGHT_STRING_SYM ( expr )',
            'function_call_conflict: WEIGHT_STRING_SYM ( expr , ulong_num , ulong_num , ulong_num )',
        ], true);
    }

    /**
     * Lowers a production this rule claims.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function call(Form $form): Scalar
    {
        $subject = $this->lowering->expressions->expression($form->node(2));
        if (isset(self::CASTS[$form->signature])) {
            [$cast, $position] = self::CASTS[$form->signature];
            $length = $this->length($form->node($position));
            $levels = count($form->node->children) > 7 ? $this->levels($form->node(6)) : [[], null];

            return new WeightString($subject, $cast, $length, ...$levels);
        }
        $numbers = $this->lowering->numbers;

        return match ($form->signature) {
            'function_call_conflict: WEIGHT_STRING_SYM ( expr opt_ws_levels )' => new WeightString($subject, null, null, ...$this->levels($form->node(3))),
            'function_call_conflict: WEIGHT_STRING_SYM ( expr )' => new WeightString($subject),
            'function_call_conflict: WEIGHT_STRING_SYM ( expr , ulong_num , ulong_num , ulong_num )' => new WeightStringParameters(
                $subject,
                $numbers->numeral($form->node(4)),
                $numbers->numeral($form->node(6)),
                $numbers->numeral($form->node(8)),
            ),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the length of the cast: a node of ws_nweights or ws_num_codepoints.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function length(Node $length): Numeral
    {
        $form = $this->lowering->form($length);
        if ($form->signature !== 'ws_nweights: ( real_ulong_num )' && $form->signature !== 'ws_num_codepoints: ( real_ulong_num )') {
            throw ImplementationGap::production($form);
        }

        return $this->lowering->numbers->numeral($form->node(1));
    }

    /**
     * Lowers the optional LEVEL clause: a node of opt_ws_levels.
     *
     * @return array{list<WeightLevel>, WeightLevelRange|null}
     * @throws ImplementationGap When a production has no rule
     */
    public function levels(Node $clause): array
    {
        $form = $this->lowering->form($clause);
        if ($form->signature === 'opt_ws_levels:') {
            return [[], null];
        }
        if ($form->signature !== 'opt_ws_levels: LEVEL_SYM ws_level_list_or_range') {
            throw ImplementationGap::production($form);
        }
        $levels = $this->lowering->form($form->node(1));
        if ($levels->signature === 'ws_level_list_or_range: ws_level_range') {
            $range = $this->lowering->form($levels->node(0));
            $this->lowering->names->claimed($range, ['ws_level_range: ws_level_number - ws_level_number']);

            return [[], new WeightLevelRange($this->number($range->node(0)), $this->number($range->node(2)))];
        }
        if ($levels->signature !== 'ws_level_list_or_range: ws_level_list') {
            throw ImplementationGap::production($levels);
        }
        $list = $levels->node(0);
        $this->lowering->names->claimed($this->lowering->form($list), ['ws_level_list: ws_level_list_item', 'ws_level_list: ws_level_list , ws_level_list_item']);
        $items = [];
        foreach ((new Lists())->items($list) as $item) {
            $items[] = $this->level($item);
        }

        return [$items, null];
    }

    /**
     * Lowers one level with its flags: a node of ws_level_list_item.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function level(Node $item): WeightLevel
    {
        $form = $this->lowering->form($item);
        $this->lowering->names->claimed($form, ['ws_level_list_item: ws_level_number ws_level_flags']);
        $flags = $this->lowering->form($form->node(1));
        [$ordered, $reverse] = self::FLAGS[$flags->signature] ?? throw ImplementationGap::production($flags);
        if ($reverse) {
            $this->lowering->names->claimed($this->lowering->form($flags->node(count($flags->node->children) - 1)), ['ws_level_flag_reverse: REVERSE_SYM']);
        }

        return new WeightLevel($this->number($form->node(0)), $ordered === null ? null : $this->direction($flags->node(0)), $reverse);
    }

    /**
     * Lowers the ASC or DESC flag of a level: a node of ws_level_flag_desc.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function direction(Node $flag): Direction
    {
        $form = $this->lowering->form($flag);

        return match ($form->signature) {
            'ws_level_flag_desc: ASC' => Direction::Ascending,
            'ws_level_flag_desc: DESC' => Direction::Descending,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a level number: a node of ws_level_number.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function number(Node $number): Numeral
    {
        $form = $this->lowering->form($number);
        $this->lowering->names->claimed($form, ['ws_level_number: real_ulong_num']);

        return $this->lowering->numbers->numeral($form->node(0));
    }
}
