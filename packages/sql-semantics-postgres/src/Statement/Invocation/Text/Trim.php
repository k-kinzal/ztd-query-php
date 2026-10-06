<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Text;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Invocation\CallTyping;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * `TRIM ([LEADING | TRAILING | BOTH] [characters] FROM strings)` and the forms without FROM.
 *
 * The server calls `pg_catalog.btrim`, `ltrim` or `rtrim` with the strings
 * written and then the characters, if written before FROM. BOTH, and FROM
 * without characters, are the defaults and are not kept. Rule: PG-TRIM-001.
 * Facts: those of the call (PG-CALL-RESULT-001; `text`, or `bytea` for
 * byte strings). The result column is named after the function called.
 * Source: https://www.postgresql.org/docs/17/functions-string.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading a trim of the end
 *     $trim = new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Text\Trim(\SqlSemantics\Platform\PostgreSql\Statement\Invocation\Text\TrimSide::Trailing, null, [new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral()]);
 *     $trim->outputName()->value // => 'rtrim'
 */
final class Trim implements Scalar, OutputNaming
{
    use Snapshot;

    /**
     * @var non-empty-list<Scalar> The strings trimmed, as written after FROM
     */
    public readonly array $strings;

    /**
     * @param TrimSide $side The ends trimmed
     * @param Scalar|null $characters The characters removed, written before FROM
     * @param list<Scalar> $strings The strings trimmed; at least one
     */
    public function __construct(public readonly TrimSide $side, public readonly ?Scalar $characters, array $strings)
    {
        $this->strings = Check::listOf($strings, Scalar::class, 'TRIM takes at least one string.', 1);
    }

    /**
     * Names an unaliased result column after the function the server calls.
     */
    public function outputName(): Name
    {
        return new Name($this->side->function());
    }

    /**
     * Derives the operands and the result of the trim function.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $characters = $this->characters === null ? null : $derivation->scalar($this->characters, $environment);
        $arguments = [];
        foreach ($this->strings as $string) {
            $arguments[] = $derivation->scalar($string, $environment);
        }
        if ($characters !== null) {
            $arguments[] = $characters;
        }

        return (new CallTyping())->catalog($derivation->context, $this->side->function(), $arguments);
    }

    /**
     * Writes TRIM with the side, the characters and the strings.
     */
    public function render(Output $out): void
    {
        $out->keyword('TRIM')->glue()->symbol('(');
        if ($this->side !== TrimSide::Both) {
            $out->keyword($this->side->value);
        }
        if ($this->characters !== null) {
            $out->node($this->characters)->keyword('FROM');
        }
        $out->list($this->strings)->symbol(')');
    }
}
