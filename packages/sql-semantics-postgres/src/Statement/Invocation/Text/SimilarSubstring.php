<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Text;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Invocation\CallTyping;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * `SUBSTRING (string SIMILAR pattern ESCAPE escape)`: the part of a string a SQL regular expression marks.
 *
 * The server calls `pg_catalog.substring(string, pattern, escape)`. Rule:
 * PG-SIMILAR-SUBSTRING-001. Facts: those of the call (PG-CALL-RESULT-001;
 * `text`). The result column is named `substring`.
 * Source: https://www.postgresql.org/docs/17/functions-matching.html#FUNCTIONS-SIMILARTO-REGEXP. Status: Implemented.
 *
 * @visibility public
 * @example Naming the result column
 *     $null = static fn () => new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral();
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Text\SimilarSubstring($null(), $null(), $null()))->outputName()->value // => 'substring'
 */
final class SimilarSubstring implements Scalar, OutputNaming
{
    use Snapshot;

    /**
     * @param Scalar $string The string
     * @param Scalar $pattern The SQL regular expression
     * @param Scalar $escape The escape character
     */
    public function __construct(public readonly Scalar $string, public readonly Scalar $pattern, public readonly Scalar $escape)
    {
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
        $arguments = [$derivation->scalar($this->string, $environment), $derivation->scalar($this->pattern, $environment), $derivation->scalar($this->escape, $environment)];

        return (new CallTyping())->catalog($derivation->context, 'substring', $arguments);
    }

    /**
     * Writes SUBSTRING with SIMILAR and ESCAPE.
     */
    public function render(Output $out): void
    {
        $out->keyword('SUBSTRING')->glue()->symbol('(')->node($this->string)->keyword('SIMILAR')->node($this->pattern)->keyword('ESCAPE')->node($this->escape)->symbol(')');
    }
}
