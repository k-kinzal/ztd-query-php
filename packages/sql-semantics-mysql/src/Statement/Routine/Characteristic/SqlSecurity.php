<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Characteristic;

use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * The SQL SECURITY characteristic of a stored routine.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/stored-objects-security.html.
 *
 * @visibility SqlSemantics
 */
final class SqlSecurity implements Characteristic
{
    use Snapshot;

    /**
     * @param SecurityContext $context Whose privileges the routine runs with
     */
    public function __construct(public readonly SecurityContext $context)
    {
    }

    /**
     * Writes SQL SECURITY and the context keyword.
     */
    public function render(Output $out): void
    {
        $out->keyword('SQL', 'SECURITY', $this->context->value);
    }
}
