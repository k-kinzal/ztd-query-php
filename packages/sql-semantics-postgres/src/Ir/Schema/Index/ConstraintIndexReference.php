<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Index;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Model\Contract\Index\IndexSchemas;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * The absolute physical index of a named primary or unique constraint.
 *
 * @visibility public
 * @example Naming the index of a declared key constraint
 *     $name = \SqlSemantics\Platform\PostgreSql\Ir\Identifier::class;
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\ConstraintIndexReference(new $name('app'), new $name('orders_key'), new $name('orders')))->quoted() // => '"app"."orders_key"'
 */
final class ConstraintIndexReference
{
    use Snapshot;

    /**
     * Fixes the declared constraint index and its ordinary local table.
     */
    public function __construct(public readonly Identifier $schema, public readonly Identifier $index, public readonly Identifier $table)
    {
        Check::input(
            !IndexSchemas::system($schema->value) && !IndexSchemas::sessionInternal($schema->value),
            'A constraint index must belong to an application schema or pg_temp.'
        );
        Check::input($index->value !== $table->value, 'A constraint index and its table must have distinct relation names.');
    }

    /**
     * Quotes the qualified physical index coordinate.
     */
    public function quoted(): string
    {
        return $this->schema->quoted() . '.' . $this->index->quoted();
    }
}
