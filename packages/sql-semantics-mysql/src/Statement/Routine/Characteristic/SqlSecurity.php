<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Characteristic;

use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * The SQL SECURITY characteristic of a stored routine.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/stored-objects-security.html.
 *
 * @visibility public
 * @example Reading the security context of a routine
 *     $alter = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('ALTER PROCEDURE p SQL SECURITY INVOKER');
 *     $alter->statement->characteristics[0]->context->value // => 'INVOKER'
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
