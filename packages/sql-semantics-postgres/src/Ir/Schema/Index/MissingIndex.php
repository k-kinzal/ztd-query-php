<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Index;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Model\Contract\Index\IndexSchemas;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A guarded selector known to lack either an index or its requested schema.
 *
 * @visibility public
 * @example Reading the kind of guarded absence
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\MissingIndex(new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('idx')))->missingSchema // => false
 */
final class MissingIndex
{
    use Snapshot;

    /**
     * Retains the searched name and, for an explicit selector, the schema and absence kind.
     */
    public function __construct(public readonly Identifier $index, public readonly ?Identifier $schema = null, public readonly bool $missingSchema = false)
    {
        Check::input($schema === null || ($schema->value !== 'pg_toast' && !IndexSchemas::sessionInternal($schema->value)), 'An absence cannot describe internal session schemas.');
        Check::input(!$missingSchema || ($schema !== null && !IndexSchemas::stock($schema->value)), 'A missing schema must name a namespace that can be absent.');
    }

    /**
     * Preserves the requested selector for the native notice.
     */
    public function quoted(): string
    {
        return ($this->schema === null ? '' : $this->schema->quoted() . '.') . $this->index->quoted();
    }
}
