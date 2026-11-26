<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Model\Statement\Type;

use SqlSemantics\Platform\MySql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * The absence of a type because the SQL request itself is semantically wrong.
 *
 * @visibility SqlSemantics
 */
final class Invalid implements TypeFact
{
    use Snapshot;

    /**
     * @param Diagnostic $cause The semantic problem that leaves the expression without a type
     */
    public function __construct(public readonly Diagnostic $cause)
    {
    }
}
