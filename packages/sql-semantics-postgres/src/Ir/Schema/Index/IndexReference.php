<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Index;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Model\Contract\Index\IndexSchemas;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * The absolute identity of an ordinary index and its indexed relation in the same schema.
 *
 * @visibility public
 * @example Reading the indexed relation's name
 *     $name = \SqlSemantics\Platform\PostgreSql\Ir\Identifier::class;
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\IndexReference(new $name('public'), new $name('idx'), new $name('accounts')))->table->value // => 'accounts'
 */
final class IndexReference
{
    use Snapshot;

    /**
     * Supplies the external identity. Use pg_temp for the current session's temporary schema.
     */
    public function __construct(public readonly Identifier $schema, public readonly Identifier $index, public readonly Identifier $table)
    {
        Check::input(!IndexSchemas::system($schema->value) && !IndexSchemas::sessionInternal($schema->value), 'An ordinary index must belong to an application schema or pg_temp.');
        Check::input($index->value !== $table->value, 'An index and its indexed table must have distinct relation names.');
    }

    /**
     * Quotes the exact index target.
     */
    public function quoted(): string
    {
        return $this->schema->quoted() . '.' . $this->index->quoted();
    }
}
