<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Placement;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * The resolved cluster table-space coordinate of an index rebuild.
 *
 * @visibility public
 * @example Selecting the built-in default table space
 *     new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Placement\IndexTablespace(new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('pg_default'))
 */
final class IndexTablespace
{
    use Snapshot;

    /**
     * Fixes the application placement or the built-in default.
     */
    public function __construct(public readonly Identifier $name)
    {
        Check::input($name->value === 'pg_default' || !str_starts_with($name->value, 'pg_'), 'An index rebuild destination requires an application tablespace or pg_default.');
    }

    /**
     * Writes the rebuild options with the selected placement.
     */
    public function prefix(bool $verbose): string
    {
        return 'REINDEX (' . ($verbose ? 'VERBOSE, ' : '') . 'TABLESPACE ' . $this->name->quoted() . ') ';
    }
}
