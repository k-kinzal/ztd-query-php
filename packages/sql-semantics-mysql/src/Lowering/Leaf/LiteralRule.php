<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Leaf;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Rules\Introducers;
use SqlSemantics\Platform\MySql\Rules\RadixSpelling;
use SqlSemantics\Platform\MySql\Rules\Strings;
use SqlSemantics\Platform\MySql\Statement\Literal\BooleanLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\EscapeRule;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;
use SqlSemantics\Platform\MySql\Statement\Literal\Radix;
use SqlSemantics\Platform\MySql\Statement\Literal\RadixLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\SignedLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers the literal productions of every release into exact values.
 *
 * Rule: MYSQL-LITERAL-001. Scope: literal, literal_or_null, null_as_literal,
 * signed_literal, signed_literal_or_null, text_literal, NUM_literal,
 * int64_literal, temporal_literal, param_marker, text_string,
 * TEXT_STRING_sys, TEXT_STRING_sys_nonewline, TEXT_STRING_literal,
 * TEXT_STRING_filesystem, TEXT_STRING_password, TEXT_STRING_hash,
 * TEXT_STRING_validated, TEXT_STRING_sys_list, json_attribute. A quoted
 * string is decoded by MYSQL-STRING-DECODE-001 under the escape setting of
 * the profile; adjacent strings are the segments of one literal; an
 * introducer or the national prefix belongs to the literal. A number keeps
 * its exact text, a hexadecimal or bit literal its digits whatever its
 * spelling. At an expression position the result is a scalar; at any other
 * position it is a text operand. Every value is recorded as an operand leaf.
 * Constructs: StringLiteral, NumberLiteral, RadixLiteral, NullLiteral,
 * BooleanLiteral, TemporalLiteral, SignedLiteral, Parameter, Text.
 * Terminates: the segment list is walked along its spine in a loop; every
 * other child is a strict subtree.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/literals.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class LiteralRule
{
    /**
     * The unit productions that pass a literal on to their only child.
     */
    private const FORWARD = [
        'literal: text_literal' => true, 'literal: NUM_literal' => true, 'literal: temporal_literal' => true,
        'literal_or_null: literal' => true, 'literal_or_null: null_as_literal' => true, 'signed_literal: literal' => true,
        'signed_literal_or_null: signed_literal' => true, 'signed_literal_or_null: null_as_literal' => true, 'NUM_literal: int64_literal' => true,
    ];

    /**
     * The productions whose only token is a number.
     */
    private const NUMBERS = [
        'NUM_literal: NUM' => true, 'NUM_literal: LONG_NUM' => true, 'NUM_literal: ULONGLONG_NUM' => true, 'NUM_literal: DECIMAL_NUM' => true,
        'NUM_literal: FLOAT_NUM' => true, 'int64_literal: NUM' => true, 'int64_literal: LONG_NUM' => true, 'int64_literal: ULONGLONG_NUM' => true,
    ];

    /**
     * The temporal literal productions, by the keyword they are written with.
     */
    private const TEMPORAL = [
        'temporal_literal: DATE_SYM TEXT_STRING' => TemporalForm::Date, 'temporal_literal: TIME_SYM TEXT_STRING' => TemporalForm::Time,
        'temporal_literal: TIMESTAMP TEXT_STRING' => TemporalForm::Timestamp, 'temporal_literal: TIMESTAMP_SYM TEXT_STRING' => TemporalForm::Timestamp,
    ];

    /**
     * The unit productions of the string rules that are read at positions that are not expressions.
     */
    private const STRINGS = [
        'TEXT_STRING_sys: TEXT_STRING' => true, 'TEXT_STRING_literal: TEXT_STRING' => true, 'TEXT_STRING_filesystem: TEXT_STRING' => true,
        'TEXT_STRING_password: TEXT_STRING' => true, 'TEXT_STRING_validated: TEXT_STRING' => true,
    ];

    /**
     * The unit productions that pass a string on to their only child.
     */
    private const STRING_FORWARD = [
        'TEXT_STRING_sys_nonewline: TEXT_STRING_sys' => true, 'TEXT_STRING_hash: TEXT_STRING_sys' => true,
        'json_attribute: TEXT_STRING_sys' => true, 'text_string: TEXT_STRING_literal' => true,
    ];

    /**
     * The productions that write a string as a hexadecimal or bit literal.
     */
    private const RADIX_TEXT = ['TEXT_STRING_hash: HEX_NUM' => Radix::Hexadecimal, 'text_string: HEX_NUM' => Radix::Hexadecimal, 'text_string: BIN_NUM' => Radix::Bit];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Answers the escape rule of the profile.
     */
    public function escapes(): EscapeRule
    {
        return EscapeRule::under($this->lowering->profile->lexical);
    }

    /**
     * Lowers a literal at an expression position.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function literal(Node $literal): Scalar
    {
        $form = $this->lowering->productions->form($literal);
        while (isset(self::FORWARD[$form->signature])) {
            $form = $this->lowering->productions->form($form->node(0));
        }
        if (isset(self::NUMBERS[$form->signature])) {
            return $this->lowering->leaves->record(new NumberLiteral($form->token(0)->text));
        }
        if (isset(self::TEMPORAL[$form->signature])) {
            return $this->lowering->leaves->record(new TemporalLiteral(self::TEMPORAL[$form->signature], $this->decode($form->token(1)->text), $this->escapes()));
        }
        if ($form->node->name === 'text_literal') {
            return $this->string($form->node);
        }

        return $this->constant($form);
    }

    /**
     * Lowers the keyword, signed, hexadecimal and bit literal productions.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function constant(Form $form): Scalar
    {
        $digits = new RadixSpelling();

        return $this->lowering->leaves->record(match ($form->signature) {
            'literal: NULL_SYM', 'null_as_literal: NULL_SYM' => new NullLiteral(),
            'literal: FALSE_SYM' => new BooleanLiteral(false),
            'literal: TRUE_SYM' => new BooleanLiteral(true),
            'literal: HEX_NUM' => new RadixLiteral(Radix::Hexadecimal, $digits->digits($form->token(0)->text)),
            'literal: BIN_NUM' => new RadixLiteral(Radix::Bit, $digits->digits($form->token(0)->text)),
            'literal: UNDERSCORE_CHARSET HEX_NUM' => new RadixLiteral(Radix::Hexadecimal, $digits->digits($form->token(1)->text), $this->introducer($form)),
            'literal: UNDERSCORE_CHARSET BIN_NUM' => new RadixLiteral(Radix::Bit, $digits->digits($form->token(1)->text), $this->introducer($form)),
            'signed_literal: + NUM_literal' => new SignedLiteral(false, $this->number($form->node(1))),
            'signed_literal: - NUM_literal' => new SignedLiteral(true, $this->number($form->node(1))),
            default => throw ImplementationGap::production($form),
        });
    }

    /**
     * Lowers an unsigned number literal.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function number(Node $number): NumberLiteral
    {
        $form = $this->lowering->productions->form($number);
        if (isset(self::FORWARD[$form->signature])) {
            $form = $this->lowering->productions->form($form->node(0));
        }
        if (!isset(self::NUMBERS[$form->signature])) {
            throw ImplementationGap::production($form);
        }

        return $this->lowering->leaves->record(new NumberLiteral($form->token(0)->text));
    }

    /**
     * Lowers a character string literal with its adjacent segments, introducer and national prefix.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function string(Node $literal): StringLiteral
    {
        $segments = [];
        $form = $this->lowering->productions->form($literal);
        while ($form->signature === 'text_literal: text_literal TEXT_STRING_literal') {
            array_unshift($segments, $this->bytes($form->node(1)));
            $form = $this->lowering->productions->form($form->node(0));
        }

        return $this->lowering->leaves->record(match ($form->signature) {
            'text_literal: TEXT_STRING' => new StringLiteral([$this->decode($form->token(0)->text), ...$segments], $this->escapes()),
            'text_literal: NCHAR_STRING' => new StringLiteral([$this->decode($form->token(0)->text), ...$segments], $this->escapes(), null, true),
            'text_literal: UNDERSCORE_CHARSET TEXT_STRING' => new StringLiteral([$this->decode($form->token(1)->text), ...$segments], $this->escapes(), $this->introducer($form)),
            default => throw ImplementationGap::production($form),
        });
    }

    /**
     * Answers the character set the introducer at the first position names.
     */
    public function introducer(Form $form): Name
    {
        return new Name((new Introducers())->charset($form->token(0)->text));
    }

    /**
     * Decodes a quoted string token under the escape setting of the profile.
     */
    public function decode(string $token): string
    {
        return (new Strings())->decode($token, !$this->lowering->profile->lexical->noBackslashEscapes);
    }

    /**
     * Answers the decoded bytes of a string rule without recording a leaf.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function bytes(Node $string): string
    {
        $form = $this->lowering->productions->form($string);
        while (isset(self::STRING_FORWARD[$form->signature])) {
            $form = $this->lowering->productions->form($form->node(0));
        }
        if (!isset(self::STRINGS[$form->signature])) {
            throw ImplementationGap::production($form);
        }

        return $this->decode($form->token(0)->text);
    }

    /**
     * Lowers a string at a position that is not an expression.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function text(Node $string): Text
    {
        $form = $this->lowering->productions->form($string);
        $radix = self::RADIX_TEXT[$form->signature] ?? null;
        if ($radix !== null) {
            return $this->lowering->leaves->record(new Text((new RadixSpelling())->digits($form->token(0)->text), $this->escapes(), $radix));
        }

        return $this->lowering->leaves->record(new Text($this->bytes($string), $this->escapes()));
    }

    /**
     * Lowers a comma-separated list of strings.
     *
     * @return list<Text>
     * @throws ImplementationGap When a production has no rule
     */
    public function texts(Node $list): array
    {
        $form = $this->lowering->productions->form($list);
        if (!in_array($form->signature, ['TEXT_STRING_sys_list: TEXT_STRING_sys', 'TEXT_STRING_sys_list: TEXT_STRING_sys_list , TEXT_STRING_sys', 'string_list: text_string', 'string_list: string_list , text_string'], true)) {
            throw ImplementationGap::production($form);
        }
        $texts = [];
        foreach ((new Lists())->items($list) as $item) {
            $texts[] = $this->text($item);
        }

        return $texts;
    }

    /**
     * Lowers a parameter marker.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function parameter(Node $marker): Parameter
    {
        $form = $this->lowering->productions->form($marker);
        if ($form->signature !== 'param_marker: PARAM_MARKER') {
            throw ImplementationGap::production($form);
        }

        return $this->lowering->leaves->record(new Parameter($form->token(0)->text));
    }
}
