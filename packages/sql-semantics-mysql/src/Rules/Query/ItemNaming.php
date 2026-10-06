<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query;

use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Platform\MySql\Statement\Literal\BooleanLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberForm;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;
use SqlSemantics\Platform\MySql\Statement\Literal\Radix;
use SqlSemantics\Platform\MySql\Statement\Literal\RadixLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\NameConversion;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Missing\MissingInput;
use SqlSemantics\Statement\Reference\Missing\SessionState;
use SqlSemantics\Statement\Scalar;

/**
 * Derives the output name of a select list item.
 *
 * Rule: MYSQL-SELECT-ITEM-NAME-001. An alias names the item. Otherwise an
 * item that names itself keeps its own name, also in parentheses or after a
 * unary plus, which create no item in the server: a column reference is
 * named by its column name as written; a string literal by the decoded
 * value of its first quoted part; NULL by `NULL`; `?` by `?`; an integer or
 * floating-point number by its text (at most 256 bytes) and a decimal number
 * by its full text; in 5.6 and 5.7 TRUE and FALSE by `TRUE` and `FALSE`;
 * NAME_CONST by the value of its name argument as text. Any other item is
 * named after the text of its expression as the statement writes it: the
 * text of its layout (MYSQL-ITEM-LAYOUT-001), or, for an item without one,
 * the canonical rendering of its expression, which is the text the server
 * then receives; an item has a layout only when it is named after a text
 * other than the canonical rendering. The text ends where the last token
 * of the expression ends (`get_cpp_tok_end()` in 5.6 and 5.7, the end of
 * the expression's `cpp` location in 8.0 and later), so whitespace and
 * comments after the expression are never part of the name and an item
 * layout has no trailing trivia (`1+1` followed by a comment is named
 * `1+1` in every release, verified on live servers).
 * The server removes the leading characters that are not graphic from the
 * text (`Name_string::copy`; for binary every byte outside ASCII is not
 * graphic), converts it from the character set it is read in to the system
 * character set (utf8mb3), and keeps at most 255 bytes, 256 when the text is
 * already in that character set (utf8mb3 and national strings, cut at a
 * byte). A text of ASCII characters is therefore its own name when it is
 * short enough, assuming an ASCII-compatible `character_set_client`; a text
 * read in `character_set_client` with other characters, or longer than 255
 * bytes, depends on the session state `character_set_client`. Binary text
 * keeps its bytes; utf8mb4 text keeps whole characters up to 255 bytes,
 * each character outside utf8mb3 written `?`. The name of text with other
 * characters in another introduced character set, and of any text in
 * ucs2, utf16, utf16le or utf32, depends on the server's conversion
 * (NameConversion). Verified on live servers of each release. Source: sql/parse_tree_items.cc
 * (`PTI_expr_with_alias::itemize`), sql/sql_yacc.yy (`select_item`), sql/item.cc
 * and sql/item.h (the constructors that set `item_name`) of each release,
 * https://dev.mysql.com/doc/refman/8.4/en/select.html,
 * https://dev.mysql.com/doc/refman/8.4/en/problems-with-alias.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ItemNaming
{
    /**
     * The most bytes of a name the server keeps when it converts the name, and when it does not.
     */
    private const LIMITS = ['convert' => 255, 'same' => 256];

    /**
     * The character sets whose characters are not single ASCII bytes.
     */
    private const WIDE = ['ucs2', 'utf16', 'utf16le', 'utf32'];

    /**
     * @param LanguageProfile $profile The profile whose release and codec the name follows
     */
    public function __construct(private readonly LanguageProfile $profile)
    {
    }

    /**
     * Answers the output name of an item, or the input its name depends on.
     *
     * An item keeps a layout only where the server names it after its text
     * and the text is not the canonical rendering, as lowering builds it;
     * another layout is an invalid construction.
     *
     * @throws ImplementationGap When NAME_CONST names the column after a value this rule does not spell
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When an item that names itself has a layout, or a layout spells the canonical rendering
     */
    public function name(SelectExpression $item): Name|MissingInput
    {
        if ($item->alias !== null) {
            return $item->alias;
        }
        $own = $this->own($item->expression);
        Check::input($item->layout === null || ($own === null && $item->layout->text() !== $this->canonical($item->expression)), 'A select item keeps a layout only when MySQL names it after a text other than its canonical rendering.');
        if ($own instanceof ColumnUse) {
            return $own->name;
        }
        if ($own instanceof Name) {
            return $own;
        }

        return $own === null ? $this->stored($this->text($item), 'client') : $this->stored($own[0], $own[1]);
    }

    /**
     * Answers the own name of an item that names itself, its text with the character set it is in, or null when the item is named after its written text.
     *
     * A column reference answers itself: it is named by its column name.
     *
     * @return ColumnUse|Name|array{string, string}|null
     * @throws ImplementationGap When NAME_CONST names the column after a value this rule does not spell
     */
    public function own(Scalar $expression): ColumnUse|Name|array|null
    {
        while ($expression instanceof Grouped || ($expression instanceof Unary && $expression->operator === UnaryOperator::Plus)) {
            $expression = $expression->operand;
        }

        return match (true) {
            $expression instanceof ColumnUse => $expression,
            $expression instanceof StringLiteral => [$expression->segments[0], $expression->introducer->value ?? ($expression->national ? 'national' : 'client')],
            $expression instanceof NullLiteral => new Name('NULL'),
            $expression instanceof Parameter => new Name('?'),
            $expression instanceof NumberLiteral => $expression->form === NumberForm::Decimal ? new Name($expression->text) : [$expression->text, 'utf8mb3'],
            $expression instanceof BooleanLiteral => $this->legacy() ? new Name($expression->value ? 'TRUE' : 'FALSE') : null,
            $expression instanceof FunctionCall && $expression->schema === null && strcasecmp($expression->name->value, 'NAME_CONST') === 0 && $expression->arguments !== [] => [$this->constant($expression->arguments[0]->expression), 'utf8mb3'],
            default => null,
        };
    }

    /**
     * Answers the value of the name argument of NAME_CONST as the server converts it to text.
     *
     * @throws ImplementationGap When the argument is a value this rule does not spell
     */
    public function constant(Scalar $argument): string
    {
        if ($argument instanceof StringLiteral) {
            return $argument->value();
        }
        if ($argument instanceof NumberLiteral && $argument->form !== NumberForm::Float) {
            [$whole, $fraction] = explode('.', $argument->text . '.');
            $whole = ltrim($whole, '0');

            return ($whole === '' ? '0' : $whole) . ($fraction === '' ? '' : '.' . $fraction);
        }
        if ($argument instanceof RadixLiteral && $argument->introducer === null) {
            return $argument->radix === Radix::Hexadecimal ? (string) hex2bin(str_pad($argument->digits, (int) (ceil(strlen($argument->digits) / 2) * 2), '0', STR_PAD_LEFT)) : $this->bytes($argument->digits);
        }
        if ($argument instanceof BooleanLiteral) {
            return $argument->value ? '1' : '0';
        }
        throw ImplementationGap::rule('the column name NAME_CONST takes from a name argument other than a string, a decimal or integer number, a hexadecimal or bit value, or a boolean');
    }

    /**
     * Answers the bytes a bit value holds, padded on the left to whole bytes.
     */
    public function bytes(string $bits): string
    {
        $bytes = '';
        foreach (str_split(str_pad($bits, (int) (max(1, ceil(strlen($bits) / 8)) * 8), '0', STR_PAD_LEFT), 8) as $byte) {
            $bytes .= chr((int) bindec($byte));
        }

        return $bytes;
    }

    /**
     * Answers the text an item is named after: its layout, or the canonical rendering of its expression.
     */
    public function text(SelectExpression $item): string
    {
        return $item->layout?->text() ?? $this->canonical($item->expression);
    }

    /**
     * Answers the text an expression is written as without a layout of its own.
     */
    public function canonical(Scalar $expression): string
    {
        $out = new Output(new Codec($this->profile->grammar));
        $out->layout(null, $expression);

        return (new Lexical())->join($out->pieces());
    }

    /**
     * Answers the name the server stores for a text read in a character set, or the input it depends on.
     *
     * The character set is client for `character_set_client`, national for
     * a national string, else the introduced character set in lower case.
     */
    public function stored(string $text, string $charset): Name|MissingInput
    {
        if (in_array($charset, self::WIDE, true)) {
            return new NameConversion($charset);
        }
        $text = ltrim($text, $charset === 'binary' ? "\x00..\x20\x7F..\xFF" : "\x00..\x20\x7F");
        $ascii = preg_match('/[\x80-\xFF]/', $text) !== 1;
        if ($charset === 'client' && (!$ascii || strlen($text) > self::LIMITS['convert'])) {
            return new SessionState('character_set_client');
        }
        if ($charset === 'utf8mb4' && !$ascii) {
            return $this->narrowed($text);
        }
        if (!$ascii && !in_array($charset, ['binary', 'utf8mb3', 'utf8', 'national'], true)) {
            return new NameConversion($charset);
        }
        $same = in_array($charset, ['utf8mb3', 'utf8', 'national'], true);

        return new Name(substr($text, 0, self::LIMITS[$same ? 'same' : 'convert']));
    }

    /**
     * Answers the name of a utf8mb4 text: each character outside utf8mb3 becomes `?`, and whole characters are kept up to 255 bytes.
     */
    public function narrowed(string $text): Name|NameConversion
    {
        $characters = mb_check_encoding($text, 'UTF-8') ? mb_str_split($text, 1, 'UTF-8') : null;
        if ($characters === null) {
            return new NameConversion('utf8mb4');
        }
        $name = '';
        foreach ($characters as $character) {
            $character = strlen($character) === 4 ? '?' : $character;
            if (strlen($name) + strlen($character) > self::LIMITS['convert']) {
                break;
            }
            $name .= $character;
        }

        return new Name($name);
    }

    /**
     * Tells whether the release reads TRUE and FALSE as integers named `TRUE` and `FALSE`.
     */
    public function legacy(): bool
    {
        return $this->profile->grammar === GrammarRelease::MySql5651 || $this->profile->grammar === GrammarRelease::MySql5744;
    }
}
