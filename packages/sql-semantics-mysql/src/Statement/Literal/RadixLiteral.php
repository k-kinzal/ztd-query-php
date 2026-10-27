<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Literal;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Introducers;
use SqlSemantics\Platform\MySql\Rules\Typing\Literals;
use SqlSemantics\Platform\MySql\Statement\LiteralExpression;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecation;
use SqlSemantics\Platform\MySql\Statement\Type\Binary;
use SqlSemantics\Platform\MySql\Statement\Type\Character;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A hexadecimal or bit-value literal: its exact digits and its optional character set introducer.
 *
 * The radix, exact digits, notation and optional introducer are retained
 * independently. Notation does not change the binary value, but is needed
 * when reconstructing SQL and when MySQL names an unaliased result column.
 *
 * Rule: MYSQL-RADIX-LITERAL-001. Facts: by default a binary string,
 * VARBINARY; with an introducer a character string, VARCHAR, of the
 * introduced character set; never NULL. In a numeric context the server
 * converts the value, which is a rule of the operator, not of the literal.
 * Precision: the length counts bytes without an introducer and characters in the introduced character set.
 * Diagnostics: none. Source: https://dev.mysql.com/doc/refman/8.4/en/hexadecimal-literals.html,
 * https://dev.mysql.com/doc/refman/8.4/en/bit-value-literals.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading one literal from either spelling
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql);
 *     [$semantics->analyze("SELECT a FROM t WHERE a = X'1F'")->query->where->right->digits, $semantics->analyze('SELECT a FROM t WHERE a = 0x1F')->toString()] // => ['1F', 'SELECT a FROM t WHERE a = 0x1F']
 */
final class RadixLiteral implements LiteralExpression
{
    use \SqlSemantics\Statement\Semantics\ScalarSemantics;
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * The retained radix notation; absent for ordinary character text and decimal numbers.
     */
    public readonly RadixNotation $notation;

    /**
     * @param Radix $radix The digit system
     * @param string $digits The digits exactly as written
     * @param Name|null $introducer The introduced character set, in lower case
     */
    public function __construct(public readonly Radix $radix, public readonly string $digits, public readonly ?Name $introducer = null, ?ScalarFact $scalarInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null, ?RadixNotation $notation = null)
    {
        if ($scalarInformation !== null) {
            $this->initializeScalarInformation($scalarInformation);
        }
        $this->notation = $notation ?? RadixNotation::canonical($radix, $digits);
        Check::input($this->notation->permits($radix, $digits), 'A radix notation accepts the literal digit count.');
        Check::input(preg_match($radix === Radix::Hexadecimal ? '/\A[0-9A-Fa-f]*\z/' : '/\A[01]*\z/', $digits) === 1, 'The digits belong to the digit system of the literal.');
        Check::input($introducer === null || (new Introducers())->known($introducer->value), 'An introducer names a character set of the server in lower case.');
    }

    /**
     * Derives the string type; a literal is never NULL.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        if ($this->introducer !== null) {
            Deprecation::charset($this->introducer->value, $derivation);
        }

        $problem = (new \SqlSemantics\Platform\MySql\Rules\Literal\Utf32Radix())->problem($this, $derivation->context->profile->grammar);
        if ($problem !== null) {
            $derivation->report($problem);
        }

        return new ScalarFact(new Known(Literals::of($derivation->context)->radix($this)), Nullability::NotNull);
    }

    /**
     * Decodes the literal's bytes, preserving leading zeros and padding incomplete octets on the left.
     *
     * @example Decoding odd hexadecimal digits
     *     bin2hex((new \SqlSemantics\Platform\MySql\Statement\Literal\RadixLiteral(\SqlSemantics\Platform\MySql\Statement\Literal\Radix::Hexadecimal, 'ABC'))->bytes()) // => '0abc'
     */
    public function bytes(): string
    {
        if ($this->digits === '') {
            return '';
        }
        if ($this->radix === Radix::Hexadecimal) {
            return (string) hex2bin(strlen($this->digits) % 2 === 1 ? '0' . $this->digits : $this->digits);
        }
        $bytes = '';
        foreach (str_split(str_pad($this->digits, (int) ceil(strlen($this->digits) / 8) * 8, '0', STR_PAD_LEFT), 8) as $octet) {
            $bytes .= chr((int) bindec($octet));
        }

        return $bytes;
    }

    /**
     * Writes the introducer and the digits in a spelling that reads back as the same digits.
     */
    public function render(Output $out): void
    {
        if ($this->introducer !== null) {
            $out->spelled('_' . $this->introducer->value);
        }
        $out->spelled($this->notation->spell($this->radix, $this->digits));
    }
}
