<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\Binding;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Model\Contract\Index\IndexSchemas;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * The absolute ordinary table identity for a PostgreSQL B-tree definition.
 * @visibility public
 * @example Selecting a table in a named application schema
 *     $name = \SqlSemantics\Platform\PostgreSql\Ir\Identifier::class;
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\Binding\IndexTarget(new $name('app'), new $name('records')))->table->value // => 'records'
 */
final class IndexTarget
{
    use Snapshot;

    /**
     * Checks the application or session-local coordinate and exact inheritance role.
     */
    public function __construct(
        public readonly Identifier $schema,
        public readonly Identifier $table,
        public readonly bool $only = false,
    ) {
        Check::input(
            !IndexSchemas::system($schema->value) && !IndexSchemas::sessionInternal($schema->value),
            'A B-tree needs a resolved ordinary application or temporary table.'
        );
    }
}
