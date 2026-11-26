<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * The removal of an ordinary SQLite table at an absolute schema location.
 *
 * @visibility public
 * @example Addressing an optional drop to the temp schema
 *     $schema = new \SqlSemantics\Platform\Sqlite\Ir\Identifier('temp');
 *     $name = new \SqlSemantics\Platform\Sqlite\Ir\Identifier('scratch');
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Schema\DropTable($schema, $name, true))->toString() // => 'DROP TABLE IF EXISTS "temp"."scratch"'
 */
final class DropTable implements SemanticsIR
{
    use Snapshot;

    /**
     * Fixes the ordinary table identity and the missing-object policy.
     */
    public function __construct(
        public readonly Identifier $schema,
        public readonly Identifier $table,
        public readonly bool $ifExists = false,
    ) {
        Check::input($schema->value !== '', 'A DROP TABLE needs a nonempty schema identifier.');
        Check::input(!str_starts_with($table->key(), 'sqlite_'), 'A DROP TABLE cannot target the reserved SQLite namespace.');
    }

    /**
     * Identifies the fixed SQLite language profile.
     */
    public function profileId(): string
    {
        return 'sqlite-3.47.2/utf8/short-column-names/numbered-parameters';
    }

    /**
     * Emits the resolved target and the policy for an absent object.
     */
    public function toString(): string
    {
        return 'DROP TABLE ' . ($this->ifExists ? 'IF EXISTS ' : '') . $this->schema->quoted() . '.' . $this->table->quoted();
    }
}
