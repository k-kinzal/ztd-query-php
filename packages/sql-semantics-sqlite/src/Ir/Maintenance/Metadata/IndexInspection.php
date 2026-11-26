<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata;

use SqlSemantics\Platform\Sqlite\Ir\Maintenance\IndexTarget;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * One read of SQLite's native metadata for an exact physical index.
 *
 * @visibility public
 * @example Reading the basic metadata heading with no named target
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\IndexInspection(\SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\IndexInspectionKind::Basic))->toString() // => 'PRAGMA index_info'
 */
final class IndexInspection implements SemanticsIR
{
    use Snapshot;

    /**
     * Fixes the operation and either a physical index identity or a checked empty selection.
     */
    public function __construct(public readonly IndexInspectionKind $kind, public readonly IndexTarget|EmptyIndexMetadata $target = new EmptyIndexMetadata())
    {
    }

    /**
     * Identifies the pinned SQLite language profile.
     */
    public function profileId(): string
    {
        return 'sqlite-3.47.2/utf8/short-column-names/numbered-parameters';
    }

    /**
     * Emits a resolved absolute index, or the original access scope of an empty selection.
     */
    public function toString(): string
    {
        $schema = $this->target->schema;
        $name = $this->target->name;
        $qualifier = $schema === null ? '' : $schema->quoted() . '.';
        $argument = $name === null ? '' : '(' . $name->quoted() . ')';

        return 'PRAGMA ' . $qualifier . $this->kind->value . $argument;
    }
}
