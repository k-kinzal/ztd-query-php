<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Literal;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Introducers;
use SqlSemantics\Platform\MySql\Rules\RadixSpelling;
use SqlSemantics\Platform\MySql\Statement\Type\Binary;
use SqlSemantics\Platform\MySql\Statement\Type\Character;
use SqlSemantics\Platform\MySql\Statement\Type\CharsetAttribute;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\BinaryKind;
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
 * A hexadecimal or bit-value literal: its exact digits and its optional character set introducer.
 *
 * `X'1F'` and `0x1F` are one literal, as are `b'101'` and `0b101`; the
 * spelling is not kept by the literal (an unaliased select list item, which
 * MySQL names after its text, keeps it in its layout). The digits are kept
 * exactly, leading zeros included.
 *
 * Rule: MYSQL-RADIX-LITERAL-001. Facts: by default a binary string,
 * VARBINARY; with an introducer a character string, VARCHAR, of the
 * introduced character set; never NULL. In a numeric context the server
 * converts the value, which is a rule of the operator, not of the literal.
 * Precision: the type family is exact; the length is not derived.
 * Diagnostics: none. Source: https://dev.mysql.com/doc/refman/8.4/en/hexadecimal-literals.html,
 * https://dev.mysql.com/doc/refman/8.4/en/bit-value-literals.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading one literal from either spelling
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql);
 *     [$semantics->analyze("SELECT a FROM t WHERE a = X'1F'")->statement->where->right->digits, $semantics->analyze('SELECT a FROM t WHERE a = 0x1F')->toString()] // => ['1F', "SELECT a FROM t WHERE a = x'1F'"]
 */
final class RadixLiteral implements Scalar
{
    use Snapshot;

    /**
     * @param Radix $radix The digit system
     * @param string $digits The digits exactly as written
     * @param Name|null $introducer The introduced character set, in lower case
     */
    public function __construct(public readonly Radix $radix, public readonly string $digits, public readonly ?Name $introducer = null)
    {
        Check::input(preg_match($radix === Radix::Hexadecimal ? '/\A[0-9A-Fa-f]*\z/' : '/\A[01]*\z/', $digits) === 1, 'The digits belong to the digit system of the literal.');
        Check::input($introducer === null || (new Introducers())->known($introducer->value), 'An introducer names a character set of the server in lower case.');
    }

    /**
     * Derives the string type; a literal is never NULL.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $type = $this->introducer === null
            ? new Binary(BinaryKind::VarBinary)
            : new Character(CharacterKind::VarChar, null, false, new CharsetAttribute(CharsetForm::Named, $this->introducer));

        return new ScalarFact(new Known($type), Nullability::NotNull);
    }

    /**
     * Writes the introducer and the digits in a spelling that reads back as the same digits.
     */
    public function render(Output $out): void
    {
        if ($this->introducer !== null) {
            $out->spelled('_' . $this->introducer->value);
        }
        $out->spelled($this->radix === Radix::Hexadecimal ? (new RadixSpelling())->hexadecimal($this->digits) : (new RadixSpelling())->bits($this->digits));
    }
}
