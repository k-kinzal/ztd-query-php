<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Literal;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Introducers;
use SqlSemantics\Platform\MySql\Rules\Typing\Literals;
use SqlSemantics\Platform\MySql\Statement\LiteralExpression;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecation;
use SqlSemantics\Platform\MySql\Statement\Type\Character;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Expression;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Missing\SessionState;
use SqlSemantics\Statement\Source\Span;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Statement\Type\TypeFact;

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
 * family is exact; the length is not derived. Diagnostics: none. Decoded
 * bytes are independent of the lexical mode used to write them.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/string-literals.html,
 * https://dev.mysql.com/doc/refman/8.4/en/charset-literal.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the value of adjacent strings
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SELECT a FROM t WHERE a = 'x' \"y\"");
 *     [$query->query->where->right->segments, $query->query->where->right->value()] // => [['x', 'y'], 'xy']
 */
final class StringLiteral implements LiteralExpression, Expression
{
    use \SqlSemantics\Statement\Semantics\ScalarSemantics;
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @var non-empty-list<string> The decoded bytes of each adjacent quoted string, in order
     */
    public readonly array $segments;

    /**
     * @param array<array-key, string|int|bool|object|null> $segments The decoded bytes of each adjacent quoted string, as a list of strings; at least one
     * @param Name|null $introducer The introduced character set, in lower case
     * @param bool $national Whether the literal is written `N'...'`; excludes an introducer
     */
    public function __construct(array $segments, public readonly ?Name $introducer = null, public readonly bool $national = false, public readonly ?Collation $collation = null, private readonly ?Span $location = null, ?ScalarFact $scalarInformation = null)
    {
        if ($scalarInformation !== null) {
            $this->initializeScalarInformation($scalarInformation);
        }
        $list = [];
        foreach ($segments as $segment) {
            Check::input(is_string($segment), 'A string literal holds decoded strings.');
            $list[] = $segment;
        }
        Check::input($list !== [] && array_is_list($segments), 'A string literal holds at least one segment.');
        Check::input($introducer === null || (!$national && (new Introducers())->known($introducer->value)), 'An introducer names a character set of the server in lower case and excludes the national form.');
        $this->segments = $list;
        Check::input($collation === null || !$national || $collation->name === 'utf8mb3_general_ci', 'A national literal uses the national character set collation.');
        Check::input($collation === null || $introducer === null || $collation->charset->name === ($introducer->value === 'utf8' ? 'utf8mb3' : $introducer->value), 'An introduced literal uses the named character set.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Answers the literal's character type, independently of any insertion destination.
     */
    public function dataType(): TypeFact
    {
        if ($this->hasScalarInformation()) {
            return $this->scalarInformation()->type;
        }
        if ($this->collation === null) {
            return new Dependent([new SessionState('the character set and collation of the string literal')]);
        }
        return new Known(Domain::string($this->collation->charset->length($this->value()), $this->collation, Field::VarString, Coercibility::Coercible));
    }

    /**
     * A character literal never evaluates to NULL.
     */
    public function nullability(): Nullability
    {
        return Nullability::NotNull;
    }

    /**
     * Answers this literal's original byte range, when it came from SQL.
     */
    public function source(): ?Span
    {
        return $this->location;
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
        if ($this->introducer !== null) {
            Deprecation::charset($this->introducer->value, $derivation);
        }
        if ($this->national) {
            Deprecation::raise(Deprecated::National, $derivation);
        }

        return new ScalarFact(new Known(Literals::of($derivation->context)->string($this)), Nullability::NotNull);
    }

    /**
     * Writes the introducer or the national prefix and each segment in single quotes.
     */
    public function render(Output $out): void
    {
        if ($this->introducer !== null) {
            $out->spelled('_' . $this->introducer->value);
        }
        foreach ($this->segments as $position => $segment) {
            $out->spelled(($this->national && $position === 0 ? 'N' : '') . $out->quote($segment));
        }
    }
}
