<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Model\Contract\Index\IndexSchemas;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A resolved namespace coordinate and its declared presence.
 *
 * @visibility public
 * @example Selecting a declared namespace
 *     new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\SchemaDropTarget(new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('archive'), true)
 */
final class SchemaDropTarget
{
    use Snapshot;

    /**
     * Fixes the name independently of the search path and session temporaries.
     */
    public function __construct(public readonly Identifier $name, public readonly bool $present)
    {
        Check::input($name->value !== 'pg_temp' && !IndexSchemas::sessionInternal($name->value), 'A schema removal needs a persistent namespace coordinate.');
    }
}
