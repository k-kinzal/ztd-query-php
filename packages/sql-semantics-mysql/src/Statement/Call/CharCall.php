<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Call\Arguments;
use SqlSemantics\Platform\MySql\Rules\Call\TypeClass;
use SqlSemantics\Platform\MySql\Rules\Typing\Texts;
use SqlSemantics\Platform\MySql\Statement\Name\CharsetName;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A call of CHAR(): the characters whose codes the arguments are, in a binary string or in the character set written after USING.
 *
 * Rule: MYSQL-CHAR-CALL-001. NULL arguments are skipped; the server still
 * marks the result as nullable, since a code that is not a character of the
 * character set gives NULL. Without USING it is a binary string, with USING a
 * character string, four characters long for each code. Terminates: the
 * arguments are strict parts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/string-functions.html#function_char.
 * Status: Implemented.
 *
 * @visibility public
 * @example Typing CHAR() with a character set
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT CHAR(77, 121 USING utf8mb4)');
 *     [$query->field(0)->type->descriptor->name(), $query->field(0)->nullability] // => ['VARCHAR', \SqlSemantics\Statement\Type\Nullability::Nullable]
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
     * Derives the codes; the result is a string that can be NULL, and in MySQL 5.6 only when a code can be (verified on a live 5.6.51 server).
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $nullability = Nullability::NotNull;
        foreach ($this->arguments as $argument) {
            $nullability = $nullability->propagate((new Arguments())->one($argument, $derivation, $environment)->nullability);
        }

        $name = $this->charset?->name?->value;
        $domain = (new Texts(Settings::of($derivation->context)))->character(count($this->arguments), $name, $derivation);

        return new ScalarFact(new Known($domain ?? ($this->charset === null ? TypeClass::Binary : TypeClass::Character)->descriptor()), $derivation->context->profile->grammar === GrammarRelease::MySql5651 ? $nullability : Nullability::Nullable);
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
