<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A data manipulation statement MySQL rejects because it breaks a rule of the language.
 *
 * @visibility public
 * @example Reading the problem of a multiple-table UPDATE with LIMIT
 *     $update = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('UPDATE t, u SET t.a = 1 LIMIT 1');
 *     $update->facts->diagnostics[0]->message() // => 'Incorrect usage of UPDATE and LIMIT'
 */
final class WriteMisuse implements Diagnostic
{
    use Snapshot;

    /**
     * @param WriteRule $rule The broken rule
     */
    public function __construct(public readonly WriteRule $rule)
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
