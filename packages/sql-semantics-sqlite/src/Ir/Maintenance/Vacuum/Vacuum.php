<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance\Vacuum;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * An in-place database rebuild, evaluated rebuild copy, or temporary no-op.
 *
 * @visibility public
 * @example Rebuilding the main database
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Maintenance\Vacuum\Vacuum())->toString() // => 'VACUUM "main"'
 */
final class Vacuum implements SemanticsIR
{
    use Snapshot;

    /**
     * Associates the resolved database with the native role of an optional operand.
     * @throws InvalidConstruction When the operand's role cannot occur on the database
     */
    public function __construct(
        public readonly Identifier $schema = new Identifier('main'),
        public readonly VacuumDestination|VacuumRegistrations|null $destination = null,
    ) {
        Check::input(
            $destination === null || ($schema->key() === 'temp') === ($destination instanceof VacuumRegistrations),
            'VACUUM accepts an evaluated destination for a persistent source or numbered registrations for temp.',
        );
    }

    /**
     * Identifies the pinned SQLite language profile.
     */
    public function profileId(): string
    {
        return 'sqlite-3.47.2/utf8/short-column-names/numbered-parameters';
    }

    /**
     * Emits a native rebuild preserving its schema, filename and registrations.
     */
    public function toString(): string
    {
        $sql = 'VACUUM ' . $this->schema->quoted();

        return $this->destination === null ? $sql : $sql . ' INTO ' . $this->destination->toString();
    }
}
