<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Literal;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Introducers;
use SqlSemantics\Platform\MySql\Rules\Strings;
use SqlSemantics\Platform\MySql\Statement\Type\Character;
use SqlSemantics\Platform\MySql\Statement\Type\CharsetAttribute;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A character string literal: its decoded segments, its optional character set introducer, and whether it is a national string.
 *
 * Quoted strings written next to each other are one literal whose value is
 * their concatenation; the segments are kept because the server names an
 * unaliased result column after the first one. `_charset'text'` has the
 * introduced character set, `N'text'` the national character set. Which
 * quote character was written has no meaning and is not kept.
 *
 * Rule: MYSQL-STRING-LITERAL-001. Facts: a character string type, VARCHAR,
 * of the introduced character set when there is one and of the national
 * character set for a national string; never NULL. The character set and
 * collation of a plain literal are those of the connection, which a context
 * does not hold, so the type names no character set. Precision: the type
 * family is exact; the length is not derived. Diagnostics: none. A literal
 * spelled under another escape rule than the profile is an invalid
 * construction. Source: https://dev.mysql.com/doc/refman/8.4/en/string-literals.html,
 * https://dev.mysql.com/doc/refman/8.4/en/charset-literal.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the value of adjacent strings
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SELECT a FROM t WHERE a = 'x' \"y\"");
 *     [$query->statement->where->right->segments, $query->statement->where->right->value()] // => [['x', 'y'], 'xy']
 */
final class StringLiteral implements Scalar
{
    use Snapshot;

    /**
     * @var non-empty-list<string> The decoded bytes of each adjacent quoted string, in order
     */
    public readonly array $segments;

    /**
     * @param array<array-key, string|int|bool|object|null> $segments The decoded bytes of each adjacent quoted string, as a list of strings; at least one
     * @param EscapeRule $escapes The rule the value is spelled under; the rule of the language profile
     * @param Name|null $introducer The introduced character set, in lower case
     * @param bool $national Whether the literal is written `N'...'`; excludes an introducer
     */
    public function __construct(array $segments, public readonly EscapeRule $escapes = EscapeRule::Backslash, public readonly ?Name $introducer = null, public readonly bool $national = false)
    {
        $list = [];
        foreach ($segments as $segment) {
            Check::input(is_string($segment), 'A string literal holds decoded strings.');
            $list[] = $segment;
        }
        Check::input($list !== [] && array_is_list($segments), 'A string literal holds at least one segment.');
        Check::input($introducer === null || (!$national && (new Introducers())->known($introducer->value)), 'An introducer names a character set of the server in lower case and excludes the national form.');
        $this->segments = $list;
    }

    /**
     * Answers the value of the literal: the concatenation of its segments.
     */
    public function value(): string
    {
        return implode('', $this->segments);
    }

    /**
     * Derives the character string type; a literal is never NULL.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        Check::input($this->escapes === EscapeRule::under($derivation->context->profile->lexical), 'A string literal must be spelled under the escape rule of the language profile.');
        $charset = $this->introducer === null ? null : new CharsetAttribute(CharsetForm::Named, $this->introducer);

        return new ScalarFact(new Known(new Character(CharacterKind::VarChar, null, $this->national, $charset)), Nullability::NotNull);
    }

    /**
     * Writes the introducer or the national prefix and each segment in single quotes.
     */
    public function render(Output $out): void
    {
        $strings = new Strings();
        if ($this->introducer !== null) {
            $out->spelled('_' . $this->introducer->value);
        }
        foreach ($this->segments as $position => $segment) {
            $out->spelled(($this->national && $position === 0 ? 'N' : '') . $strings->encode($segment, $this->escapes === EscapeRule::Backslash));
        }
    }
}
