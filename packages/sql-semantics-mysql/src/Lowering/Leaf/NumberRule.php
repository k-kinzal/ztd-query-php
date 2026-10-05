<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Leaf;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Rules\RadixSpelling;
use SqlSemantics\Platform\MySql\Statement\Literal\ByteSize;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;

/**
 * Lowers the number productions of positions that are not expressions.
 *
 * Rule: MYSQL-NUMERAL-001. Scope: ulong_num, real_ulong_num, ulonglong_num,
 * real_ulonglong_num, dec_num, dec_num_error, size_number,
 * option_autoextend_size, ternary_option, and the bare NUM tokens of other
 * families' productions. A number keeps its exact text; a
 * hexadecimal literal keeps its digits. The rules real_ulong_num and
 * real_ulonglong_num accept a decimal or floating number through
 * dec_num_error, which the server rejects when it parses; the number is
 * structured as written and the statement that holds it reports the
 * problem. A size is a number or a word such as `16M`. Constructs: Numeral,
 * ByteSize. Terminates: unit productions over strict subtrees.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/number-literals.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class NumberRule
{
    /**
     * The productions whose only token is a number, by whether the token is a hexadecimal literal.
     */
    private const TOKENS = [
        'ulong_num: NUM' => false, 'ulong_num: HEX_NUM' => true, 'ulong_num: LONG_NUM' => false, 'ulong_num: ULONGLONG_NUM' => false,
        'ulong_num: DECIMAL_NUM' => false, 'ulong_num: FLOAT_NUM' => false, 'real_ulong_num: NUM' => false, 'real_ulong_num: HEX_NUM' => true,
        'real_ulong_num: LONG_NUM' => false, 'real_ulong_num: ULONGLONG_NUM' => false, 'ulonglong_num: NUM' => false,
        'ulonglong_num: ULONGLONG_NUM' => false, 'ulonglong_num: LONG_NUM' => false, 'ulonglong_num: DECIMAL_NUM' => false,
        'ulonglong_num: FLOAT_NUM' => false, 'real_ulonglong_num: NUM' => false, 'real_ulonglong_num: ULONGLONG_NUM' => false,
        'real_ulonglong_num: LONG_NUM' => false, 'real_ulonglong_num: HEX_NUM' => true, 'dec_num: DECIMAL_NUM' => false, 'dec_num: FLOAT_NUM' => false,
    ];

    /**
     * The unit productions that pass a number on to their only child.
     */
    private const FORWARD = ['real_ulong_num: dec_num_error' => true, 'real_ulonglong_num: dec_num_error' => true, 'dec_num_error: dec_num' => true];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a number.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function numeral(Node $number): Numeral
    {
        $form = $this->lowering->productions->form($number);
        while (isset(self::FORWARD[$form->signature])) {
            $form = $this->lowering->productions->form($form->node(0));
        }
        $hexadecimal = self::TOKENS[$form->signature] ?? throw ImplementationGap::production($form);
        $text = $form->token(0)->text;

        return $this->lowering->leaves->record(new Numeral($hexadecimal ? (new RadixSpelling())->digits($text) : $text, $hexadecimal));
    }

    /**
     * Lowers a bare NUM token that a production of another family holds, such as the count of `IGNORE 2 LINES`.
     *
     * Productions such as `opt_ignore_lines`, `func_datetime_precision` or
     * `vcpu_num_or_range` take the NUM token itself instead of a number
     * nonterminal; the token is a decimal integer and keeps its exact text.
     */
    public function token(Token $token): Numeral
    {
        Check::invariant($token->is('NUM'), 'A bare number is a NUM token.');

        return $this->lowering->leaves->record(new Numeral($token->text));
    }

    /**
     * Lowers a size in bytes, written alone or as the AUTOEXTEND_SIZE option.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function size(Node $size): ByteSize
    {
        $form = $this->lowering->productions->form($size);

        return match ($form->signature) {
            'size_number: real_ulonglong_num' => $this->lowering->leaves->record(new ByteSize($this->numeral($form->node(0)))),
            'size_number: IDENT_sys' => $this->lowering->leaves->record(new ByteSize(null, $this->lowering->names->identifier($form->node(0)))),
            'option_autoextend_size: AUTOEXTEND_SIZE_SYM opt_equal size_number' => $this->size($form->node(2)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers an option value that is a number or the keyword DEFAULT; DEFAULT is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function ternary(Node $option): ?Numeral
    {
        $form = $this->lowering->productions->form($option);

        return match ($form->signature) {
            'ternary_option: ulong_num' => $this->numeral($form->node(0)),
            'ternary_option: DEFAULT_SYM' => null,
            default => throw ImplementationGap::production($form),
        };
    }
}
