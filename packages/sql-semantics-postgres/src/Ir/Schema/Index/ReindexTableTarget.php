<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Index;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Model\Contract\Index\IndexSchemas;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * The absolute local identity of the owner of an index set.
 *
 * @visibility public
 * @example Naming a session-local index owner
 *     $identifier = \SqlSemantics\Platform\PostgreSql\Ir\Identifier::class;
 *     $target = new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\ReindexTableTarget(new $identifier('pg_temp'), new $identifier('orders'));
 *     $target->quoted() // => '"pg_temp"."orders"'
 */
final class ReindexTableTarget
{
    use Snapshot;

    /**
     * References a standalone local owner under the same declared relation interpretation.
     */
    public function __construct(
        public readonly Identifier $schema,
        public readonly Identifier $name,
        public readonly ReindexTableKind $kind = ReindexTableKind::BaseTable,
    ) {
        Check::input(!IndexSchemas::system($schema->value) && !IndexSchemas::sessionInternal($schema->value), 'An index owner requires an application schema or pg_temp.');
        Check::input($schema->value !== 'pg_temp' || $kind === ReindexTableKind::BaseTable, 'Only base tables are temporary index owners.');
    }

    /**
     * Quotes the exact owner coordinate.
     */
    public function quoted(): string
    {
        return $this->schema->quoted() . '.' . $this->name->quoted();
    }
}
