<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Leaf;

use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Program\Variable;
use MySqlMemory\Typing\Domain;
use Override;

/**
 * A read of a parameter or local variable of a running stored program, or of a column of the NEW or OLD row of a trigger: the value it holds now, of its declared type.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/local-variable-scope.html.
 *
 * @visibility MySqlMemory
 */
final class ProgramRead implements Evaluable
{
    /**
     * @param Variable $variable The variable read
     */
    public function __construct(public readonly Variable $variable)
    {
    }

    /**
     * Answers the declared type of the variable, which can be NULL.
     */
    #[Override]
    public function domain(): Domain
    {
        return $this->variable->domain->withNullable(true);
    }

    /**
     * Reads the variable.
     */
    #[Override]
    public function evaluate(Frame $frame): int|float|string|null
    {
        return $this->variable->value;
    }
}
