<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Type;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Type\CharsetAttribute;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\BinaryMark;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\NumericModifier;

/**
 * Lowers the parts a data type is written with: lengths, precisions, numeric attributes and character set attributes.
 *
 * Rule: MYSQL-TYPE-PART-001. Scope: field_length, opt_field_length,
 * precision, opt_precision, float_options, standard_float_options,
 * type_datetime_precision, field_options, field_opt_list, field_option,
 * opt_binary, opt_bin_mod, opt_charset_with_opt_binary, ascii, unicode. A
 * length keeps its exact text; numeric attributes keep their written order;
 * a character set attribute keeps the place of BINARY. Constructs:
 * NumericModifier, CharsetAttribute. Terminates: the attribute list is
 * flattened iteratively; every other child is a strict subtree.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/data-types.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class TypePartRule
{
    /**
     * The productions that write one number in parentheses.
     */
    private const LENGTHS = [
        'field_length: ( LONG_NUM )' => true, 'field_length: ( ULONGLONG_NUM )' => true, 'field_length: ( DECIMAL_NUM )' => true,
        'field_length: ( NUM )' => true, 'type_datetime_precision: ( NUM )' => true,
    ];

    /**
     * The productions that write no length or precision.
     */
    private const EMPTY = [
        'opt_field_length:' => true, 'type_datetime_precision:' => true, 'opt_precision:' => true, 'float_options:' => true,
        'standard_float_options:' => true,
    ];

    /**
     * The unit productions that pass a length or precision on to their only child.
     */
    private const FORWARD = [
        'opt_field_length: field_length' => true, 'opt_precision: precision' => true, 'float_options: field_length' => true,
        'float_options: precision' => true, 'standard_float_options: field_length' => true,
    ];

    /**
     * The numeric attribute productions.
     */
    private const MODIFIERS = [
        'field_option: SIGNED_SYM' => NumericModifier::Signed, 'field_option: UNSIGNED' => NumericModifier::Unsigned,
        'field_option: UNSIGNED_SYM' => NumericModifier::Unsigned, 'field_option: ZEROFILL' => NumericModifier::Zerofill,
        'field_option: ZEROFILL_SYM' => NumericModifier::Zerofill,
    ];

    /**
     * The ASCII and UNICODE productions, by the form and the place of BINARY.
     */
    private const SHORTHANDS = [
        'ascii: ASCII_SYM' => [CharsetForm::Ascii, BinaryMark::Absent], 'ascii: BINARY ASCII_SYM' => [CharsetForm::Ascii, BinaryMark::Leading],
        'ascii: ASCII_SYM BINARY' => [CharsetForm::Ascii, BinaryMark::Trailing], 'ascii: BINARY_SYM ASCII_SYM' => [CharsetForm::Ascii, BinaryMark::Leading],
        'ascii: ASCII_SYM BINARY_SYM' => [CharsetForm::Ascii, BinaryMark::Trailing], 'unicode: UNICODE_SYM' => [CharsetForm::Unicode, BinaryMark::Absent],
        'unicode: UNICODE_SYM BINARY' => [CharsetForm::Unicode, BinaryMark::Trailing], 'unicode: BINARY UNICODE_SYM' => [CharsetForm::Unicode, BinaryMark::Leading],
        'unicode: UNICODE_SYM BINARY_SYM' => [CharsetForm::Unicode, BinaryMark::Trailing], 'unicode: BINARY_SYM UNICODE_SYM' => [CharsetForm::Unicode, BinaryMark::Leading],
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers an optional length or fractional seconds precision to its exact text.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function length(Node $length): ?string
    {
        return $this->numbers($length)[0];
    }

    /**
     * Lowers an optional length, or precision and scale, to their exact texts.
     *
     * @return array{string|null, string|null}
     * @throws ImplementationGap When the production has no rule
     */
    public function numbers(Node $options): array
    {
        $form = $this->lowering->productions->form($options);
        if (isset(self::FORWARD[$form->signature])) {
            $form = $this->lowering->productions->form($form->node(0));
        }
        if (isset(self::EMPTY[$form->signature])) {
            return [null, null];
        }
        if (isset(self::LENGTHS[$form->signature])) {
            return [$form->token(1)->text, null];
        }

        return $form->signature === 'precision: ( NUM , NUM )' ? [$form->token(1)->text, $form->token(3)->text] : throw ImplementationGap::production($form);
    }

    /**
     * Lowers the numeric attributes in the order written.
     *
     * @return list<NumericModifier>
     * @throws ImplementationGap When a production has no rule
     */
    public function modifiers(Node $options): array
    {
        $form = $this->lowering->productions->form($options);
        if ($form->signature === 'field_options:') {
            return [];
        }
        if ($form->signature !== 'field_options: field_opt_list') {
            throw ImplementationGap::production($form);
        }
        $list = $this->lowering->productions->form($form->node(0));
        if ($list->signature !== 'field_opt_list: field_opt_list field_option' && $list->signature !== 'field_opt_list: field_option') {
            throw ImplementationGap::production($list);
        }
        $modifiers = [];
        foreach ((new Lists())->items($form->node(0)) as $item) {
            $option = $this->lowering->productions->form($item);
            $modifiers[] = self::MODIFIERS[$option->signature] ?? throw ImplementationGap::production($option);
        }

        return $modifiers;
    }

    /**
     * Tells whether the optional BINARY attribute after a character set or a national type is written.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function binary(Node $modifier): bool
    {
        $form = $this->lowering->productions->form($modifier);

        return match ($form->signature) {
            'opt_bin_mod:' => false,
            'opt_bin_mod: BINARY', 'opt_bin_mod: BINARY_SYM' => true,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the BINARY attribute of a national character type.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function nationalAttribute(Node $modifier): ?CharsetAttribute
    {
        return $this->binary($modifier) ? new CharsetAttribute(CharsetForm::Binary) : null;
    }

    /**
     * Lowers the character set attribute of a character type; none is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function charset(Node $attribute): ?CharsetAttribute
    {
        $form = $this->lowering->productions->form($attribute);
        $names = $this->lowering->charsets;

        return match ($form->signature) {
            'opt_binary:', 'opt_charset_with_opt_binary:' => null,
            'opt_binary: ascii', 'opt_binary: unicode', 'opt_charset_with_opt_binary: ascii', 'opt_charset_with_opt_binary: unicode' => $this->shorthand($form->node(0)),
            'opt_binary: BYTE_SYM', 'opt_charset_with_opt_binary: BYTE_SYM' => new CharsetAttribute(CharsetForm::Byte),
            'opt_binary: BINARY', 'opt_charset_with_opt_binary: BINARY_SYM' => new CharsetAttribute(CharsetForm::Binary),
            'opt_binary: charset charset_name opt_bin_mod', 'opt_charset_with_opt_binary: character_set charset_name opt_bin_mod' => new CharsetAttribute($this->named($form->node(0)), $names->name($form->node(1)), $this->binary($form->node(2)) ? BinaryMark::Trailing : BinaryMark::Absent),
            'opt_binary: BINARY charset charset_name', 'opt_charset_with_opt_binary: BINARY_SYM character_set charset_name' => new CharsetAttribute($this->named($form->node(1)), $names->name($form->node(2)), BinaryMark::Leading),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers ASCII or UNICODE with the place of its BINARY attribute.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function shorthand(Node $shorthand): CharsetAttribute
    {
        $form = $this->lowering->productions->form($shorthand);
        [$charset, $mark] = self::SHORTHANDS[$form->signature] ?? throw ImplementationGap::production($form);

        return new CharsetAttribute($charset, null, $mark);
    }

    /**
     * Answers how a character set is introduced: `CHARSET`, or `CHARACTER SET` and `CHAR SET`, which the lexer reads as one keyword and SET.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function named(Node $keyword): CharsetForm
    {
        $form = $this->lowering->form($keyword);

        return match ($form->signature) {
            'charset: CHARSET', 'character_set: CHARSET' => CharsetForm::Named,
            'charset: CHAR_SYM SET', 'character_set: CHAR_SYM SET_SYM' => CharsetForm::CharacterSet,
            default => throw ImplementationGap::production($form),
        };
    }
}
