<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Expression\Conditional;

use SqlSemantics\Statement\Expression\ScalarExpression;
use SqlSemantics\Statement\SemanticGraph;

/**
 * One ordered test and the value evaluated only when that test matches.
 * @visibility public
 * @example Keeping a selected NULL distinct from an absent ELSE branch
 *     $null = new \SqlSemantics\Statement\Expression\NullConstant();
 *     (new \SqlSemantics\Statement\Expression\Conditional\SqliteCaseArm($null, $null))->toString() // => 'WHEN NULL THEN NULL'
 */
final class SqliteCaseArm
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * The enclosing CASE determines whether the test is a truth test or a comparison value.
     */
    public function __construct(public readonly ScalarExpression $test, public readonly ScalarExpression $result)
    {
        \SqlSemantics\Statement\Validation\Check::input((new SemanticGraph())->containsOnlyValues($this), 'A CASE branch retains only semantic operands.');
    }

    /**
     * Keeps the test and result boundaries without evaluating either operand.
     */
    public function toString(): string
    {
        return 'WHEN ' . $this->test->toString() . ' THEN ' . $this->result->toString();
    }
}
