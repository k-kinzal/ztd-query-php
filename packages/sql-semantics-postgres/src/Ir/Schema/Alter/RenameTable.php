<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Alter;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\TableName;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A persistent table name change within its resolved schema.
 *
 * @visibility public
 * @example Renaming a persistent table
 *     $semantics = new \SqlSemantics\Platform\PostgreSql\Semantics();
 *     $declarations = $semantics->context(['CREATE TABLE reports (id integer)']);
 *     $semantics->analyze('ALTER TABLE reports RENAME TO archived', $declarations)->toString() // => 'ALTER TABLE "public"."reports" RENAME TO "archived"'
 */
final class RenameTable implements SemanticsIR
{
    use Snapshot;

    /**
     * Fixes the table identity, destination and optional absence policy.
     */
    public function __construct(
        public readonly TableName $target,
        public readonly Identifier $destination,
        public readonly bool $ifExists = false,
        public readonly GrammarRelease $release = GrammarRelease::PostgreSql172,
    ) {
        Check::input($ifExists || $target->table->value !== $destination->value, 'A table rename requires a distinct destination name.');
    }

    /**
     * Identifies the fixed native PostgreSQL profile.
     */
    public function profileId(): string
    {
        return $this->release->value . '/utf8/standard-conforming-strings/native';
    }

    /**
     * Emits the table name change in the owning schema.
     */
    public function toString(): string
    {
        return 'ALTER TABLE ' . ($this->ifExists ? 'IF EXISTS ' : '') . $this->target->quoted()
            . ' RENAME TO ' . $this->destination->quoted();
    }
}
