<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Contract\Index\IndexSchemas;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * Renames one persistent namespace while retaining its catalog identity.
 *
 * @visibility public
 * @example Renaming an archive namespace
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\RenameSchema(new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('archive'), new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('cold.archive')))->toString() // => 'ALTER SCHEMA "archive" RENAME TO "cold.archive"'
 */
final class RenameSchema implements SemanticsIR
{
    use Snapshot;

    /**
     * Selects the source namespace and its new name.
     */
    public function __construct(
        public readonly Identifier $schema,
        public readonly Identifier $destination,
        public readonly GrammarRelease $release = GrammarRelease::PostgreSql172,
    ) {
        Check::input($schema->value !== 'pg_temp' && !IndexSchemas::sessionInternal($schema->value), 'A schema rename requires a persistent namespace.');
        Check::input($schema->value !== $destination->value && !str_starts_with($destination->value, 'pg_'), 'A schema rename requires a distinct unreserved destination.');
    }

    /**
     * Identifies the native language profile.
     */
    public function profileId(): string
    {
        return $this->release->value . '/utf8/standard-conforming-strings/native';
    }

    /**
     * Emits the source and destination in their separate roles.
     */
    public function toString(): string
    {
        return 'ALTER SCHEMA ' . $this->schema->quoted() . ' RENAME TO ' . $this->destination->quoted();
    }
}
