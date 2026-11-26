<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Model\Statement\Declaration;

use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * An external SQLite trigger declaration with its absolute watched relation.
 *
 * @visibility SqlSemantics
 */
final class Trigger
{
    use Snapshot;

    /**
     * Stores an installed object coordinate without retaining its program.
     */
    public function __construct(
        public readonly Name $schema,
        public readonly Name $name,
        public readonly Name $watchedSchema,
        public readonly Name $watchedName,
    ) {
        $comparison = Comparison::AsciiInsensitive;
        Check::input(!str_starts_with($comparison->fold($name->value), 'sqlite_'), 'An ordinary trigger cannot belong to the reserved SQLite namespace.');
        Check::input($comparison->equal($schema->value, 'temp') || $comparison->equal($schema->value, $watchedSchema->value), 'A stored trigger must watch a relation in its own schema.');
    }
}
