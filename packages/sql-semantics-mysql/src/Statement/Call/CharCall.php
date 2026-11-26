<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Known;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\MySql\Rules\Call\Arguments;
use SqlSemantics\Platform\MySql\Rules\Call\TypeClass;
use SqlSemantics\Platform\MySql\Statement\Name\CharsetName;

/**
 * A call of CHAR(): the characters whose codes the arguments are, in a binary string or in the character set written after USING.
 *
 * Rule: MYSQL-CHAR-CALL-001. NULL arguments are skipped, so the result is
 * never NULL; without USING it is a binary string, with USING a character
 * string. Terminates: the arguments are strict parts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/string-functions.html#function_char.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class CharCall implements Scalar
{
    use Snapshot;

    /**
     * @var list<Scalar> The character codes in order
     */
    public readonly array $arguments;

    /**
     * @param list<Scalar> $arguments The character codes in order; at least one
     * @param CharsetName|null $charset The character set written after USING
     */
    public function __construct(array $arguments, public readonly ?CharsetName $charset = null)
    {
        $this->arguments = Check::listOf($arguments, Scalar::class, 'CHAR() takes at least one expression.', 1);
    }

    /**
     * Derives the codes; the result is a string that is never NULL.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        foreach ($this->arguments as $argument) {
            (new Arguments())->one($argument, $derivation, $environment);
        }

        return new ScalarFact(new Known(($this->charset === null ? TypeClass::Binary : TypeClass::Character)->descriptor()), Nullability::NotNull);
    }

    /**
     * Writes the call.
     */
    public function render(Output $out): void
    {
        $out->keyword('CHAR')->glue()->symbol('(')->list($this->arguments);
        if ($this->charset !== null) {
            $out->keyword('USING')->node($this->charset);
        }
        $out->symbol(')');
    }
}
