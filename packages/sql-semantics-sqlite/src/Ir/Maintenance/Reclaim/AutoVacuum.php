<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance\Reclaim;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * Reads or requests the automatic reclamation mode of one resolved database.
 *
 * @visibility public
 * @example Reading the main database's active mode
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Maintenance\Reclaim\AutoVacuum())->toString() // => 'PRAGMA "main".auto_vacuum'
 */
final class AutoVacuum implements SemanticsIR
{
    use Snapshot;

    /**
     * Fixes the scope and distinguishes a mode read from every supplied request.
     */
    public function __construct(
        public readonly Identifier $schema = new Identifier('main'),
        public readonly ?VacuumMode $mode = null,
    ) {
    }

    /**
     * Identifies the pinned SQLite language profile.
     */
    public function profileId(): string
    {
        return 'sqlite-3.47.2/utf8/short-column-names/numbered-parameters';
    }

    /**
     * Emits the resolved schema and the exact native mode when supplied.
     */
    public function toString(): string
    {
        $argument = $this->mode === null ? '' : '(' . $this->mode->value . ')';

        return 'PRAGMA ' . $this->schema->quoted() . '.auto_vacuum' . $argument;
    }
}
