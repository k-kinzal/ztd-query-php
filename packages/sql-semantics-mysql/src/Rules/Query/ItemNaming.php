<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query;

use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Expression\Access\OdbcEscape;
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
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
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
 * named by its column name as written, except that a reference resolved to a
 * stored-program variable uses the complete expression spelling; a string literal by the decoded
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
 * bytes, depends on the session state `character_set_client` unless the
 * session settings name it, when it is read as text in that character set. Binary text
 * keeps its bytes; utf8mb4 text keeps whole characters up to 255 bytes,
 * each character outside utf8mb3 written `?`, and so does latin1 text,
 * read as cp1252 whose unassigned bytes are the C1 controls. Text in ucs2, utf16, utf16le
 * or utf32 is read in whole units of its bytes, a unit that is no character written `?`.
 * The name of text with other characters in another introduced character set depends on
 * the server's conversion (NameConversion). Verified on live servers of each release. Source: sql/parse_tree_items.cc
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
     * @param \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings|null $settings Session-dependent interpretation of ODBC temporal escapes
     * @param Charset|null $client The character set of the session's character_set_client; null when it is not known
     */
    public function __construct(private readonly LanguageProfile $profile, private readonly ?Charset $client = null, private readonly ?\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings $settings = null)
    {
    }

    /**
     * Answers the output name of an item, or the input its name depends on.
     *
     * An item keeps a layout where its resolved role can make the server name
     * it after its text and the text is not the canonical rendering;
     * another layout is an invalid construction.
     *
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When an item that names itself has a layout, or a layout spells the canonical rendering
     */
    public function name(SelectExpression|\SqlSemantics\Platform\MySql\Statement\Call\CallArgument $item, ?\SqlSemantics\Resolution\Environment $environment = null): Name|MissingInput
    {
        if ($item->alias !== null) {
            $text = ltrim($item->alias->value, "\x00..\x20\x7F");

            return $this->identifier($text === $item->alias->value ? $item->alias : new Name($text));
        }
        $own = $this->own($item->expression);
        Check::input($item->layout === null || ($this->keeps($item->expression) && $this->text($item) !== $this->canonical($item->expression)), 'A select item keeps a layout only when MySQL names it after a text other than its canonical rendering.');
        if ($own instanceof ColumnUse) {
            $variable = $environment !== null && $own->qualifier === null ? (new \SqlSemantics\Platform\MySql\Rules\ProgramVariables())->find($environment, $own->name) : null;
            if ($variable instanceof \SqlSemantics\Statement\Reference\Column\ResolvedColumn) {
                return $this->stored($this->text($item), 'client');
            }
            return $this->identifier($own->name);
        }
        if ($own instanceof Name) {
            return $own;
        }

        return $own === null ? $this->stored($this->text($item), 'client') : $this->stored($own[0], $own[1]);
    }

    /**
     * Tells whether expression spelling can determine a result name.
     *
     * An unqualified column use may resolve to a stored-program variable; its
     * result then keeps the entire expression spelling, including wrappers.
     */
    public function keeps(Scalar $expression): bool
    {
        $own = $this->own($expression, false);

        return $own === null || ($own instanceof ColumnUse && $own->qualifier === null);
    }

    /**
     * Answers a decoded identifier, stopping at the first NUL as MySQL does for output names.
     *
     * Identifier character sets are interpreted during lowering. A constructed name already
     * denotes its system-character-set value and never changes with the execution session.
     */
    public function identifier(Name $name): Name
    {
        return str_contains($name->value, "\0") ? new Name(explode("\0", $name->value, 2)[0]) : $name;
    }

    /**
     * Answers the own name of an item that names itself, its text with the character set it is in, or null when the item is named after its written text.
     *
     * A column reference answers itself: it is named by its column name. Parentheses, a unary
     * plus and an ODBC escape that stands for its operand name the item as their operand does.
     *
     * @return ColumnUse|Name|array{string, string}|null
     */
    public function own(Scalar $expression, bool $resolved = true): ColumnUse|Name|array|null
    {
        $expression = $this->unwrapped($expression, $resolved);

        return match (true) {
            $expression instanceof ColumnUse => $expression,
            $expression instanceof StringLiteral => [$expression->segments[0], $expression->introducer->value ?? ($expression->national ? 'national' : 'client')],
            $expression instanceof NullLiteral => new Name('NULL'),
            $expression instanceof Parameter => new Name('?'),
            $expression instanceof NumberLiteral => $expression->form === NumberForm::Decimal ? new Name($expression->text) : [$expression->text, 'utf8mb3'],
            $expression instanceof BooleanLiteral => $this->legacy() ? new Name($expression->value ? 'TRUE' : 'FALSE') : null,
            $expression instanceof FunctionCall && $expression->schema === null && strcasecmp($expression->name->value, 'NAME_CONST') === 0 && $expression->arguments !== [] => [\SqlSemantics\Platform\MySql\Rules\Literal\NameBytes::format($this->constant($expression->arguments[0]->expression), $this->legacy()), 'utf8mb3'],
            default => null,
        };
    }

    /**
     * Answers the expression an item is named by: the operand of the parentheses, unary plus and
     * ODBC escapes that stand for their operand around it, which create no item in the server.
     */
    public function unwrapped(Scalar $expression, bool $resolved = true): Scalar
    {
        while ($expression instanceof Grouped || ($expression instanceof Unary && $expression->operator === UnaryOperator::Plus) || ($expression instanceof OdbcEscape && $expression->literal($resolved ? $this->settings : null) === null)) {
            $expression = $expression->operand;
        }

        return $expression;
    }

    /**
     * Answers the value of the name argument of NAME_CONST as the server converts it to text.
     *
     * A name that is not a literal, NULL among them, is empty, as the server refuses the call.
     *
     */
    public function constant(Scalar $argument): string
    {
        $argument = (new \SqlSemantics\Platform\MySql\Rules\Call\NamedConstant())->unwrapped($argument, $this->settings);
        if ($argument instanceof StringLiteral) {
            return $argument->value();
        }
        if ($argument instanceof NumberLiteral && $argument->form === NumberForm::Float) {
            return \SqlSemantics\Platform\MySql\Rules\Literal\Real::format((float) $argument->text, $this->legacy() ? PHP_INT_MAX : 22);
        }
        if ($argument instanceof \SqlSemantics\Platform\MySql\Statement\Literal\TemporalLiteral) {
            return \SqlSemantics\Platform\MySql\Rules\Literal\Temporal::literal($argument->form === \SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm::Timestamp ? 'DATETIME' : $argument->form->value, $argument->text, (int) $argument->type()->precision, false, false, true) ?? '';
        }
        if ($argument instanceof NumberLiteral) {
            [$whole, $fraction] = explode('.', $argument->text . '.');
            $whole = ltrim($whole, '0');

            return ($whole === '' ? '0' : $whole) . ($fraction === '' ? '' : '.' . $fraction);
        }
        if ($argument instanceof RadixLiteral) {
            return $argument->radix === Radix::Hexadecimal ? (string) hex2bin(str_pad($argument->digits, (int) (ceil(strlen($argument->digits) / 2) * 2), '0', STR_PAD_LEFT)) : $this->bytes($argument->digits);
        }
        if ($argument instanceof BooleanLiteral) {
            return $argument->value ? '1' : '0';
        }
        return '';
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
    public function text(SelectExpression|\SqlSemantics\Platform\MySql\Statement\Call\CallArgument $item): string
    {
        $out = new Output(new Codec($this->profile->grammar, $this->profile->lexical));
        $out->layout($item->layout, $item->expression, $item->spellingProfile?->grammar === GrammarRelease::MySql5651);

        return $this->encoded($out);
    }

    /**
     * Answers the text an expression is written as without a layout of its own.
     */
    public function canonical(Scalar $expression): string
    {
        $out = new Output(new Codec($this->profile->grammar, $this->profile->lexical));
        $out->layout(null, $expression);

        return $this->encoded($out);
    }

    /**
     * Encodes name slots for an observable result label, replacing characters
     * the client character set cannot represent. Literal slots already hold bytes.
     */
    public function encoded(Output $output): string
    {
        $pieces = array_map(fn (\SqlSemantics\Rendering\Piece $piece): \SqlSemantics\Rendering\Piece => new \SqlSemantics\Rendering\Piece($piece->kind, $piece->kind === \SqlSemantics\Rendering\PieceKind::Name ? (new \SqlSemantics\Platform\MySql\Rules\Text\NameEncoding())->encode($piece->text, $this->client) : $piece->text, $piece->glued, $piece->gap), $output->pieces());

        return (new Lexical())->join($pieces);
    }

    /**
     * Answers the name the server stores for a text read in a character set, or the input it depends on.
     *
     * The character set is client for `character_set_client`, national for
     * a national string, else the introduced character set in lower case;
     * a known `character_set_client` names the character set of client.
     */
    public function stored(string $text, string $charset): Name|MissingInput
    {
        if ($charset === 'client' && $this->client !== null) {
            $charset = strtolower($this->client->name);
        }
        if (in_array($charset, self::WIDE, true)) {
            return $this->narrowed(explode("\0", ltrim($this->wide($charset === 'ucs2' ? ltrim($text, "\x00..\x20") : $text, $charset), "\x00..\x20"), 2)[0]);
        }
        $text = ltrim($text, $charset === 'binary' ? "\x00..\x20\x7F..\xFF" : "\x00..\x20\x7F");
        $text = explode("\0", $text, 2)[0];
        $ascii = preg_match('/[\x80-\xFF]/', $text) !== 1;
        if ($charset === 'client' && (!$ascii || strlen($text) > self::LIMITS['convert'])) {
            return new SessionState('character_set_client');
        }
        if ($charset === 'utf8mb4' && !$ascii) {
            return $this->narrowed($text);
        }
        if ($charset === 'latin1' && !$ascii) {
            return $this->narrowed($this->latin1($text));
        }
        if (!$ascii && !in_array($charset, ['binary', 'utf8mb3', 'utf8', 'national'], true)) {
            return new NameConversion($charset);
        }
        $same = in_array($charset, ['utf8mb3', 'utf8', 'national'], true);

        return new Name(substr($text, 0, self::LIMITS[$same ? 'same' : 'convert']));
    }

    /**
     * Answers in UTF-8 the bytes of a text written for a character set of wide units, UCS-2, UTF-16, UTF-16LE or UTF-32, as the server names a column after it.
     *
     * The bytes are read in whole units, a trailing part of a unit is dropped, and a unit or a
     * pair of UTF-16 units that is no character becomes `?` (verified on a live 8.4 server).
     */
    public function wide(string $text, string $charset): string
    {
        $unit = $charset === 'utf32' ? 4 : 2;
        $codes = [];
        foreach (str_split(substr($text, 0, strlen($text) - strlen($text) % $unit), $unit) as $bytes) {
            if ($bytes === '') {
                continue;
            }
            $codes[] = match ($charset) {
                'utf16le' => ord($bytes[0]) | (ord($bytes[1]) << 8),
                'utf32' => (ord($bytes[0]) << 24) | (ord($bytes[1]) << 16) | (ord($bytes[2]) << 8) | ord($bytes[3]),
                default => (ord($bytes[0]) << 8) | ord($bytes[1]),
            };
        }
        $converted = '';
        for ($index = 0; $index < count($codes); $index++) {
            $code = $codes[$index];
            $next = $codes[$index + 1] ?? null;
            if ($charset !== 'utf32' && $charset !== 'ucs2' && $code >= 0xD800 && $code <= 0xDBFF && $next !== null && $next >= 0xDC00 && $next <= 0xDFFF) {
                $converted .= mb_chr(0x10000 + (($code - 0xD800) << 10) + ($next - 0xDC00), 'UTF-8');
                $index++;
                continue;
            }
            $converted .= ($code >= 0xD800 && $code <= 0xDFFF) || $code > 0x10FFFF ? '?' : mb_chr($code, 'UTF-8');
        }

        return $converted;
    }

    /**
     * Answers a latin1 text in UTF-8: latin1 is cp1252, whose five unassigned bytes stand for the C1 controls of the same code.
     */
    public function latin1(string $text): string
    {
        return (new \SqlSemantics\Platform\MySql\Rules\Text\NameEncoding())->decode($text, Charset::known('latin1'));
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
