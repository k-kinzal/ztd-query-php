<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A LOAD statement with incompatible input, format or algorithm options.
 *
 * @visibility public
 * @example Reading the reason for a LOAD refusal
 *     (new \SqlSemantics\Platform\MySql\Statement\Dml\Problem\LoadConflict(\SqlSemantics\Platform\MySql\Statement\Dml\Problem\LoadRule::XmlBulk))->message() // => 'Incorrect usage of LOAD XML and BULK Algorithm'
 */
final class LoadConflict implements Diagnostic
{
    use Snapshot;

    /**
     * @param LoadRule $rule The incompatible combination
     */
    public function __construct(public readonly LoadRule $rule)
    {
    }

    /**
     * Describes the conflict without consulting the input SQL.
     */
    public function message(): string
    {
        return $this->rule->value;
    }
}
