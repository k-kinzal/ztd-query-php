<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Query\Problem;

use SqlSemantics\Platform\Sqlite\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A request SQLite rejects because it breaks a rule of the language.
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
     * Describes the broken rule in the words of SQLite.
     */
    public function message(): string
    {
        return $this->rule->value;
    }
}
