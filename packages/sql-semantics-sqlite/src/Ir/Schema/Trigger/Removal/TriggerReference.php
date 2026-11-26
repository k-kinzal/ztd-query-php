<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Removal;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * The absolute identity of an installed SQLite trigger and the relation it watches.
 *
 * @visibility public
 * @example Inspecting the watched relation of a temporary trigger
 *     $semantics = new \SqlSemantics\Platform\Sqlite\Semantics();
 *     $declarations = $semantics->context(['CREATE TABLE t (id INTEGER)', 'CREATE TEMP TRIGGER a AFTER INSERT ON main.t BEGIN SELECT NEW.id; END']);
 *     $drop = $semantics->analyze('DROP TRIGGER a', $declarations);
 *     [$drop->target->schema->value, $drop->target->watchedSchema->value] // => ['temp', 'main']
 */
final class TriggerReference
{
    use Snapshot;

    /**
     * Checks that the installed object can watch the declared relation.
     */
    public function __construct(
        public readonly Identifier $schema,
        public readonly Identifier $name,
        public readonly Identifier $watchedSchema,
        public readonly Identifier $watchedName,
    ) {
        Check::input(!str_starts_with($name->key(), 'sqlite_'), 'An ordinary trigger cannot belong to the reserved SQLite namespace.');
        Check::input($schema->key() === 'temp' || $schema->key() === $watchedSchema->key(), 'A stored trigger must watch a relation in its own schema.');
    }

    /**
     * Emits the absolute trigger identifier for a schema operation.
     */
    public function quoted(): string
    {
        return $this->schema->quoted() . '.' . $this->name->quoted();
    }
}
