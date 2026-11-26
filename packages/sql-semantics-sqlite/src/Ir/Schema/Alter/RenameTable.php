<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Alter;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A table rename within one resolved SQLite schema.
 *
 * @visibility public
 * @example Renaming a temporary table
 *     $semantics = new \SqlSemantics\Platform\Sqlite\Semantics();
 *     $declarations = $semantics->context(['CREATE TEMP TABLE t (value INTEGER)']);
 *     $semantics->analyze('ALTER TABLE t RENAME TO renamed', $declarations)->toString() // => 'ALTER TABLE "temp"."t" RENAME TO "renamed"'
 */
final class RenameTable implements SemanticsIR
{
    use Snapshot;

    /**
     * Binds the destination to the current schema and checks both table names.
     */
    public function __construct(
        public readonly Identifier $schema,
        public readonly Identifier $table,
        public readonly Identifier $destination,
    ) {
        Check::input($schema->value !== '', 'An ALTER TABLE target needs a nonempty schema identifier.');
        Check::input(
            !str_starts_with($table->key(), 'sqlite_') && !str_starts_with($destination->key(), 'sqlite_'),
            'A table rename cannot use the reserved SQLite namespace.',
        );
        Check::input($table->key() !== $destination->key(), 'A table rename requires a distinct destination name.');
    }

    /**
     * Identifies the fixed SQLite language profile.
     */
    public function profileId(): string
    {
        return 'sqlite-3.47.2/utf8/short-column-names/numbered-parameters';
    }

    /**
     * Emits the absolute table target and its new name in the same schema.
     */
    public function toString(): string
    {
        return 'ALTER TABLE ' . $this->schema->quoted() . '.' . $this->table->quoted() . ' RENAME TO ' . $this->destination->quoted();
    }
}
