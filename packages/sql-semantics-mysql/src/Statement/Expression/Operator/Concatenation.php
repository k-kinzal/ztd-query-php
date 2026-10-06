<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Operator;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Expression\Operands;
use SqlSemantics\Platform\MySql\Rules\Expression\Precedence;
use SqlSemantics\Platform\MySql\Rules\Expression\StringResult;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * The string concatenation `a || b` of the PIPES_AS_CONCAT mode (`Item_func_concat`).
 *
 * Without PIPES_AS_CONCAT `||` is a synonym of OR, so this form exists only
 * under that mode and is rejected by the derivation otherwise. It is the
 * weakest operator of the simple_expr level and associates to the left.
 *
 * Rule: MYSQL-CONCATENATION-001. Facts: a VARCHAR, or a VARBINARY when an
 * operand is a binary string (MYSQL-STRING-RESULT-001); NULL when an
 * operand is. Terminates: the operands are strict parts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/sql-mode.html#sqlmode_pipes_as_concat,
 * https://dev.mysql.com/doc/refman/8.4/en/string-functions.html#function_concat.
 * Status: Implemented.
 *
 * @visibility public
 * @example Concatenating under PIPES_AS_CONCAT
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, null, \SqlSemantics\Platform\MySql\Mode::fromString('PIPES_AS_CONCAT'));
 *     $semantics->analyze("SELECT a FROM t WHERE a || 'x'")->statement->where instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Operator\Concatenation // => true
 */
final class Concatenation implements Scalar
{
    use Snapshot;

    /**
     * @param Scalar $left The left operand
     * @param Scalar $right The right operand
     */
    public function __construct(public readonly Scalar $left, public readonly Scalar $right)
    {
        $precedence = new Precedence();
        Check::input($precedence->fits($left, Precedence::SIMPLE_EXPR, Precedence::SIMPLE_EXPR), 'The left operand of || needs a grouping to keep its place.');
        Check::input($precedence->opening($right) > Precedence::SIMPLE_EXPR, 'The right operand of || needs a grouping to keep its place.');
    }

    /**
     * Derives both operands and the string type of the result.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        Check::input($derivation->context->profile->lexical->pipesAsConcat, 'The concatenation operator || exists only under PIPES_AS_CONCAT.');
        $operands = new Operands();
        $left = $operands->single($derivation->scalar($this->left, $environment), $derivation);
        $right = $operands->single($derivation->scalar($this->right, $environment), $derivation);

        return new ScalarFact((new StringResult())->concatenation([$left->type, $right->type]), $left->nullability->propagate($right->nullability));
    }

    /**
     * Writes the operands around `||`.
     */
    public function render(Output $out): void
    {
        $out->node($this->left)->symbol('||')->node($this->right);
    }
}
