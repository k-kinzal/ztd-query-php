<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Text;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Rules\Invocation\CallTyping;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;

/**
 * `OVERLAY (string PLACING replacement FROM start [FOR count])`: a substring replaced.
 *
 * The server calls `pg_catalog.overlay(string, replacement, start[, count])`.
 * Rule: PG-OVERLAY-001. Facts: those of the call (PG-CALL-RESULT-001; the
 * type of the string for `text`, `bytea` and `bit`). The result column is
 * named `overlay`.
 * Source: https://www.postgresql.org/docs/17/functions-string.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 * @example Reading an overlay without a count
 *     $null = static fn () => new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral();
 *     $overlay = new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Text\Overlay($null(), $null(), $null());
 *     [$overlay->count, $overlay->outputName()->value] // => [null, 'overlay']
 */
final class Overlay implements Scalar, OutputNaming
{
    use Snapshot;

    /**
     * @param Scalar $string The string changed
     * @param Scalar $replacement The string placed
     * @param Scalar $start The position of the first character replaced
     * @param Scalar|null $count The number of characters replaced; the length of the replacement when null
     */
    public function __construct(public readonly Scalar $string, public readonly Scalar $replacement, public readonly Scalar $start, public readonly ?Scalar $count = null)
    {
    }

    /**
     * Names an unaliased result column after the function the server calls.
     */
    public function outputName(): Name
    {
        return new Name('overlay');
    }

    /**
     * Derives the operands and the result of `pg_catalog.overlay`.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $arguments = [$derivation->scalar($this->string, $environment), $derivation->scalar($this->replacement, $environment), $derivation->scalar($this->start, $environment)];
        if ($this->count !== null) {
            $arguments[] = $derivation->scalar($this->count, $environment);
        }

        return (new CallTyping())->catalog($derivation->context, 'overlay', $arguments);
    }

    /**
     * Writes OVERLAY with its keywords.
     */
    public function render(Output $out): void
    {
        $out->keyword('OVERLAY')->glue()->symbol('(')->node($this->string)->keyword('PLACING')->node($this->replacement)->keyword('FROM')->node($this->start);
        if ($this->count !== null) {
            $out->keyword('FOR')->node($this->count);
        }
        $out->symbol(')');
    }
}
