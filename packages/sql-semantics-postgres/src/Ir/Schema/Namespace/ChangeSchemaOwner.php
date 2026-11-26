<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Contract\Index\IndexSchemas;
use SqlSemantics\Platform\PostgreSql\Model\Contract\StockRoles;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * Changes the owning role of one resolved persistent namespace.
 *
 * @visibility public
 * @example Assigning the effective role as the archive owner
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\ChangeSchemaOwner(new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('archive'), \SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\SchemaSessionRole::Effective))->toString() // => 'ALTER SCHEMA "archive" OWNER TO CURRENT_USER'
 */
final class ChangeSchemaOwner implements SemanticsIR
{
    use Snapshot;

    /**
     * Fixes the existing namespace identity and new owning role.
     */
    public function __construct(
        public readonly Identifier $schema,
        public readonly Identifier|SchemaSessionRole $owner,
        public readonly GrammarRelease $release = GrammarRelease::PostgreSql172,
    ) {
        Check::input($schema->value !== 'pg_temp' && !IndexSchemas::sessionInternal($schema->value), 'A schema owner change requires a persistent namespace.');
        if ($owner instanceof Identifier) {
            Check::input($owner->value !== 'public' && $owner->value !== 'none', 'A named schema owner needs a single role name.');
            Check::input(!str_starts_with($owner->value, 'pg_') || StockRoles::contains($release, $owner->value), 'The stock schema owner must exist in this release.');
        }
    }

    /**
     * Identifies the native language profile.
     */
    public function profileId(): string
    {
        return $this->release->value . '/utf8/standard-conforming-strings/native';
    }

    /**
     * Emits the two checked identities.
     */
    public function toString(): string
    {
        $owner = $this->owner instanceof Identifier ? $this->owner->quoted() : $this->owner->value;

        return 'ALTER SCHEMA ' . $this->schema->quoted() . ' OWNER TO ' . $owner;
    }
}
