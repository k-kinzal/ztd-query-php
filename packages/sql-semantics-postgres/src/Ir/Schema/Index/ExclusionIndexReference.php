<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Index;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Model\Contract\Index\IndexSchemas;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * The physical index of a named PostgreSQL exclusion constraint.
 * @visibility public
 * @example Naming the index of an exclusion constraint
 *     $name = \SqlSemantics\Platform\PostgreSql\Ir\Identifier::class;
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\ExclusionIndexReference(new $name('app'), new $name('no_overlap'), new $name('bookings')))->quoted() // => '"app"."no_overlap"'
 */
final class ExclusionIndexReference
{
    use Snapshot;

    /**
     * Fixes the index and its owning table in the same application namespace.
     */
    public function __construct(public readonly Identifier $schema, public readonly Identifier $index, public readonly Identifier $table)
    {
        Check::input(
            !IndexSchemas::system($schema->value) && !IndexSchemas::sessionInternal($schema->value),
            'An exclusion index must belong to an application schema or pg_temp.'
        );
        Check::input($index->value !== $table->value, 'An exclusion index and its table must have distinct relation names.');
    }

    /**
     * Quotes the absolute coordinate of the physical index.
     */
    public function quoted(): string
    {
        return $this->schema->quoted() . '.' . $this->index->quoted();
    }
}
