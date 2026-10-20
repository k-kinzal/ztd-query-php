<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * A data manipulation statement MySQL rejects because it breaks a rule of the language.
 *
 * @visibility public
 * @example Reading the retained problem of a stored multiple-table UPDATE with LIMIT
 *     $update = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p() UPDATE t, u SET t.a = 1 LIMIT 1');
 *     $update->facts->diagnostics[0]->message() // => 'Incorrect usage of UPDATE and LIMIT'
 */
final class WriteMisuse implements Diagnostic
{
    use Snapshot;

    /**
     * @param WriteRule $rule The broken rule
     * @param Name|null $table The correlation name of the target table that is not updatable, or its name when it has none
     * @param \SqlSemantics\Platform\MySql\Statement\Dml\MultipleDelete|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredMultipleDelete|null $statement The owning write, when its preparation role matters
     */
    public function __construct(public readonly WriteRule $rule, public readonly ?Name $table = null, public readonly \SqlSemantics\Platform\MySql\Statement\Dml\MultipleDelete|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredMultipleDelete|null $statement = null)
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
