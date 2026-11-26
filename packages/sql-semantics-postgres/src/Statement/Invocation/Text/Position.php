<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Text;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Precedence;
use SqlSemantics\Platform\PostgreSql\Rules\Invocation\CallTyping;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;

/**
 * `POSITION (substring IN string)`: where a substring first occurs.
 *
 * The server calls `pg_catalog.position(string, substring)`, the operands
 * swapped. Both operands are restricted expressions (`b_expr`): an operand
 * that would need parentheses there is rejected. Rule: PG-POSITION-001.
 * Facts: those of the call (PG-CALL-RESULT-001; `integer`). The result
 * column is named `position`.
 * Source: https://www.postgresql.org/docs/17/functions-string.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 * @example Naming the result column
 *     $position = new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Text\Position(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral(), new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral());
 *     $position->outputName()->value // => 'position'
 */
final class Position implements Scalar, OutputNaming
{
    use Snapshot;

    /**
     * @param Scalar $substring The substring searched for
     * @param Scalar $string The string searched
     */
    public function __construct(public readonly Scalar $substring, public readonly Scalar $string)
    {
        $precedence = new Precedence();
        Check::input($precedence->restricted($substring) && $precedence->restricted($string), 'The operands of POSITION are restricted expressions; group an operand that is not.');
    }

    /**
     * Names an unaliased result column after the function the server calls.
     */
    public function outputName(): Name
    {
        return new Name('position');
    }

    /**
     * Derives the operands and the result of `pg_catalog.position`.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $substring = $derivation->scalar($this->substring, $environment);
        $string = $derivation->scalar($this->string, $environment);

        return (new CallTyping())->catalog($derivation->context, 'position', [$string, $substring]);
    }

    /**
     * Writes POSITION with the substring and the string.
     */
    public function render(Output $out): void
    {
        $out->keyword('POSITION')->glue()->symbol('(')->node($this->substring)->keyword('IN')->node($this->string)->symbol(')');
    }
}
