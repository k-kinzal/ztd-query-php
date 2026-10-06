<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Text;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Invocation\CallTyping;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * `SUBSTRING (string FROM start FOR count)` and its partial forms.
 *
 * The server calls `pg_catalog.substring(string, start[, count])`. With FOR
 * alone it passes `1` as the start and the count cast to `integer`; FOR may
 * also be written before FROM, which the model keeps because the tokens
 * differ. With FROM alone and a text start the call is the POSIX pattern
 * form. Rule: PG-SUBSTRING-001. Facts: those of the call
 * (PG-CALL-RESULT-001; the type of the string for `text`, `bytea` and
 * `bit`). The result column is named `substring`.
 * Source: https://www.postgresql.org/docs/17/functions-string.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading a substring with a count only
 *     $substring = new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Text\Substring(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral(), null, new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral());
 *     [$substring->start, $substring->outputName()->value] // => [null, 'substring']
 * @example Rejecting a substring without start and count
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Text\Substring(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral()) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class Substring implements Scalar, OutputNaming
{
    use Snapshot;

    /**
     * @param Scalar $string The string
     * @param Scalar|null $start The position of the first character, after FROM
     * @param Scalar|null $count The number of characters, after FOR
     * @param bool $countFirst Whether FOR is written before FROM
     */
    public function __construct(public readonly Scalar $string, public readonly ?Scalar $start = null, public readonly ?Scalar $count = null, public readonly bool $countFirst = false)
    {
        Check::input($start !== null || $count !== null, 'SUBSTRING takes FROM, FOR or both.');
        Check::input(!$countFirst || ($start !== null && $count !== null), 'FOR is written before FROM only when both are written.');
    }

    /**
     * Names an unaliased result column after the function the server calls.
     */
    public function outputName(): Name
    {
        return new Name('substring');
    }

    /**
     * Derives the operands and the result of `pg_catalog.substring`.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $string = $derivation->scalar($this->string, $environment);
        $start = $this->start === null ? new ScalarFact(new Known(Builtin::Int4), Nullability::NotNull) : $derivation->scalar($this->start, $environment);
        $arguments = [$string, $start];
        if ($this->count !== null) {
            $count = $derivation->scalar($this->count, $environment);
            $arguments[] = $this->start === null ? new ScalarFact(new Known(Builtin::Int4), $count->nullability) : $count;
        }

        return (new CallTyping())->catalog($derivation->context, 'substring', $arguments);
    }

    /**
     * Writes SUBSTRING with FROM and FOR in the order written.
     */
    public function render(Output $out): void
    {
        $out->keyword('SUBSTRING')->glue()->symbol('(')->node($this->string);
        if ($this->countFirst && $this->count !== null) {
            $out->keyword('FOR')->node($this->count);
        }
        if ($this->start !== null) {
            $out->keyword('FROM')->node($this->start);
        }
        if (!$this->countFirst && $this->count !== null) {
            $out->keyword('FOR')->node($this->count);
        }
        $out->symbol(')');
    }
}
