<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Conversion;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Expression\Operands;
use SqlSemantics\Platform\MySql\Statement\Name\CharsetName;
use SqlSemantics\Platform\MySql\Statement\Type\Binary;
use SqlSemantics\Platform\MySql\Statement\Type\Character;
use SqlSemantics\Platform\MySql\Statement\Type\CharsetAttribute;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\BinaryKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Known;

/**
 * A conversion of a string to a character set: `CONVERT(expr USING charset)` (`Item_func_conv_charset`).
 *
 * Rule: MYSQL-CHARSET-CONVERSION-001. Facts: a VARCHAR in the named
 * character set, or a VARBINARY for the character set `binary`; NULL when
 * the operand is. Terminates: the operand is a strict part.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/cast-functions.html#function_convert.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the character set
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SELECT a FROM t WHERE CONVERT(a USING utf8mb4) = 'x'");
 *     $query->statement->where->left->charset->name->value // => 'utf8mb4'
 */
final class CharsetConversion implements Scalar
{
    use Snapshot;

    /**
     * @param Scalar $operand The converted expression
     * @param CharsetName $charset The character set; not DEFAULT
     */
    public function __construct(public readonly Scalar $operand, public readonly CharsetName $charset)
    {
        Check::input($charset->name !== null, 'CONVERT … USING names a character set.');
    }

    /**
     * Derives the operand; the result is a string in the character set.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $fact = (new Operands())->single($derivation->scalar($this->operand, $environment), $derivation);
        $name = $this->charset->name;
        Check::invariant($name !== null, 'CONVERT … USING names a character set.');
        $type = strtolower($name->value) === 'binary' ? new Binary(BinaryKind::VarBinary) : new Character(CharacterKind::VarChar, null, false, new CharsetAttribute(CharsetForm::Named, $name));

        return new ScalarFact(new Known($type), $fact->nullability);
    }

    /**
     * Writes CONVERT, the operand, USING and the character set in parentheses.
     */
    public function render(Output $out): void
    {
        $out->keyword('CONVERT')->glue()->symbol('(')->node($this->operand)->keyword('USING')->node($this->charset)->symbol(')');
    }
}
