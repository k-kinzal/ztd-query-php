<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A read or transition of a built-in SQLite connection flag.
 *
 * @visibility public
 * @example Reading the current foreign key setting
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Maintenance\FlagPragma(\SqlSemantics\Platform\Sqlite\Ir\Maintenance\FlagName::ForeignKeys))->toString() // => 'PRAGMA foreign_keys'
 */
final class FlagPragma implements SemanticsIR
{
    use Snapshot;

    /**
     * Selects the flag, the optional transition, and the optional database coordinate.
     * @throws InvalidConstruction When a schema reload is requested for another flag
     */
    public function __construct(
        public readonly FlagName $name,
        public readonly ?FlagChange $change = null,
        public readonly ?Identifier $schema = null,
    ) {
        Check::input($change !== FlagChange::Reset || $name === FlagName::WritableSchema, 'Only writable_schema can reload the schema cache.');
    }

    /**
     * Identifies the pinned SQLite language profile.
     */
    public function profileId(): string
    {
        return 'sqlite-3.47.2/utf8/short-column-names/numbered-parameters';
    }

    /**
     * Writes the resolved flag operation with its database qualifier when present.
     */
    public function toString(): string
    {
        $qualifier = $this->schema === null ? '' : $this->schema->quoted() . '.';
        $argument = $this->change === null ? '' : ' = ' . $this->change->value;

        return 'PRAGMA ' . $qualifier . $this->name->value . $argument;
    }
}
