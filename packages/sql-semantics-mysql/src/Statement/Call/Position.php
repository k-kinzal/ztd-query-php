<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Call\Arguments;
use SqlSemantics\Platform\MySql\Rules\Call\ResultTyping;
use SqlSemantics\Platform\MySql\Rules\Expression\Precedence;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * A call of POSITION(substring IN string): the position of the first occurrence, 0 when there is none.
 *
 * Rule: MYSQL-POSITION-CALL-001. The server builds LOCATE(substring,
 * string): an integer that is NULL when an argument is. The substring is an
 * arithmetic operand (bit_expr) that ends before IN; an operand that would
 * absorb IN needs a grouping and is refused. Terminates: the operands are
 * strict parts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/string-functions.html#function_position.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading both operands of POSITION()
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SELECT POSITION('b' IN 'abc')");
 *     $query->field(0)->type->descriptor->name() // => 'BIGINT'
 */
final class Position implements Scalar
{
    use Snapshot;

    /**
     * @param Scalar $substring The string to find; an arithmetic operand
     * @param Scalar $string The string to search
     */
    public function __construct(public readonly Scalar $substring, public readonly Scalar $string)
    {
        $precedence = new Precedence();
        Check::input($precedence->admits($substring, Precedence::BIT_EXPR) && !$precedence->absorbs($substring, Precedence::PREDICATE, Precedence::BIT_EXPR), 'The substring of POSITION needs a grouping.');
    }

    /**
     * Derives the operands; the result is an integer.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        return (new ResultTyping())->fact('IP', [(new Arguments())->one($this->substring, $derivation, $environment), (new Arguments())->one($this->string, $derivation, $environment)]);
    }

    /**
     * Writes the call.
     */
    public function render(Output $out): void
    {
        $out->keyword('POSITION')->glue()->symbol('(')->node($this->substring)->keyword('IN')->node($this->string)->symbol(')');
    }
}
