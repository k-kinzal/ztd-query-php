<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Call\Arguments;
use SqlSemantics\Platform\MySql\Rules\Call\ResultTyping;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * A call of TRIM(): a string without the leading, trailing or both occurrences of a removed string, by default spaces.
 *
 * Rule: MYSQL-TRIM-CALL-001. The result is a string in the character set of
 * the arguments and is NULL when an argument is. The eight written forms
 * are kept: with or without a side, with or without the removed string.
 * Without a side and a removed string there is no FROM. Terminates: the
 * operands are strict parts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/string-functions.html#function_trim.
 * Status: Implemented.
 *
 * @visibility public
 * @example Holding the parts of TRIM(LEADING 'x' FROM a)
 *     $trim = new \SqlSemantics\Platform\MySql\Statement\Call\Trim(new \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse(new \SqlSemantics\Statement\Identifier\Name('a')), \SqlSemantics\Platform\MySql\Statement\Call\TrimSide::Leading, new \SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral(['x']));
 *     [$trim->side, $trim->removed !== null] // => [\SqlSemantics\Platform\MySql\Statement\Call\TrimSide::Leading, true]
 */
final class Trim implements Scalar
{
    use Snapshot;

    /**
     * @param Scalar $subject The string to trim
     * @param TrimSide|null $side The side, when written
     * @param Scalar|null $removed The string to remove, when written
     */
    public function __construct(public readonly Scalar $subject, public readonly ?TrimSide $side = null, public readonly ?Scalar $removed = null)
    {
    }

    /**
     * Derives the operands; the result is a string.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $facts = [];
        if ($this->removed !== null) {
            $facts[] = (new Arguments())->one($this->removed, $derivation, $environment);
        }
        $facts[] = (new Arguments())->one($this->subject, $derivation, $environment);

        return (new ResultTyping())->fact('SP', $facts);
    }

    /**
     * Writes the call in the form it was written.
     */
    public function render(Output $out): void
    {
        $out->keyword('TRIM')->glue()->symbol('(');
        if ($this->side !== null) {
            $out->keyword($this->side->value);
        }
        $out->node($this->removed);
        if ($this->side !== null || $this->removed !== null) {
            $out->keyword('FROM');
        }
        $out->node($this->subject)->symbol(')');
    }
}
