<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query\Problem;

use SqlSemantics\Platform\MySql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A query MySQL rejects because it breaks a rule of the language.
 *
 * @visibility SqlSemantics
 */
final class Misuse implements Diagnostic
{
    use Snapshot;

    /**
     * @param MisuseRule $rule The broken rule
     */
    public function __construct(public readonly MisuseRule $rule)
    {
    }

    /**
     * Describes the broken rule in the words of the server.
     */
    public function message(): string
    {
        return $this->rule->value;
    }
}
